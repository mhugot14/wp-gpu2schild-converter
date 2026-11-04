<?php
/**
 * Release-Zip bauen (übernommen aus webuntisAnalyser bzw. mh_form_workflows).
 *
 * Packt nur, was auf dem Server gebraucht wird: composer.json, die Hauptdatei, uninstall.php,
 * src, templates und vendor - wie der Zip von 1.0.0. Entwicklungsdateien (CLAUDE.md, docs,
 * tests, tools) und vor allem uploads/ (kann echte GPU002-Dateien enthalten) bleiben draußen.
 *
 * vendor/ wird in einem temporären Ordner mit "composer install --no-dev" neu aufgebaut,
 * damit keine Entwicklungspakete auf den Server gelangen. Das lokale vendor/ bleibt
 * unverändert.
 *
 * Bewusst über ZipArchive statt PowerShell Compress-Archive: Windows PowerShell 5.1
 * schreibt Backslashes in die Pfade, WordPress auf einem Linux-Server legt daraus
 * Dateien wie "includes\viewer\View.php" an statt Ordnern - das Plugin startet dann nicht.
 *
 * Die Version wird aus dem Plugin-Header gelesen (MH_USC_VERSION muss dazu passen).
 *
 * Aufruf (aus dem Plugin-Verzeichnis):
 *   php tools/build-release-zip.php
 *
 * Ergebnis: ../webuntisAnalyser_v<Version>.zip (neben dem Plugin-Ordner).
 * Ein vorhandener Zip derselben Version wird nicht überschrieben.
 */

declare(strict_types=1);

if ( 'cli' !== PHP_SAPI ) {
	exit( "Dieses Skript läuft nur auf der Kommandozeile.\n" );
}
if ( ! class_exists( ZipArchive::class ) ) {
	exit( "PHP-Erweiterung zip fehlt (ZipArchive).\n" );
}

$base    = dirname( __DIR__ );
$slug    = basename( $base );
$include = [ 'composer.json', $slug . '.php', 'uninstall.php', 'src', 'templates', 'vendor' ];

$main = (string) file_get_contents( $base . '/' . $slug . '.php' );
preg_match( '/^\s*\*?\s*Version:\s*(\S+)/m', $main, $header );
$version = $header[1] ?? '';
if ( '' === $version ) {
	exit( "Keine Version im Plugin-Header gefunden.\n" );
}

$target = dirname( $base ) . '/' . $slug . '_v' . $version . '.zip';
if ( file_exists( $target ) ) {
	exit( "Existiert bereits: $target\nVersion erhöhen oder den alten Zip entfernen.\n" );
}

// vendor/ ohne Entwicklungspakete in einem temporären Ordner bauen. src/ wird
// mitkopiert, damit die optimierte Classmap die Plugin-Klassen enthält (PSR-4 auf src/).
$build = sys_get_temp_dir() . '/mh_usc_build_' . bin2hex( random_bytes( 4 ) );
if ( ! mkdir( $build ) ) {
	exit( "Temporärer Ordner konnte nicht angelegt werden: $build\n" );
}
copy( $base . '/composer.json', $build . '/composer.json' );
copy( $base . '/composer.lock', $build . '/composer.lock' );
copy_dir( $base . '/src', $build . '/src' );

$cmd = 'composer install --no-dev --optimize-autoloader --no-interaction --no-progress --quiet --working-dir=' . escapeshellarg( $build );
passthru( $cmd, $exit );
if ( 0 !== $exit || ! is_file( $build . '/vendor/autoload.php' ) ) {
	remove_dir( $build );
	exit( "composer install --no-dev ist fehlgeschlagen (Exit $exit).\n" );
}

$zip = new ZipArchive();
if ( true !== $zip->open( $target, ZipArchive::CREATE ) ) {
	remove_dir( $build );
	exit( "Zip konnte nicht angelegt werden: $target\n" );
}

$count = 0;
foreach ( $include as $entry ) {
	$root = 'vendor' === $entry ? $build : $base;
	$path = $root . '/' . $entry;
	if ( is_file( $path ) ) {
		$zip->addFile( $path, $slug . '/' . $entry );
		$count++;
		continue;
	}
	if ( ! is_dir( $path ) ) {
		echo "Hinweis: $entry nicht vorhanden, übersprungen.\n";
		continue;
	}

	$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $files as $file ) {
		$relative = str_replace( '\\', '/', substr( $file->getPathname(), strlen( $root ) + 1 ) );
		$zip->addFile( $file->getPathname(), $slug . '/' . $relative );
		$count++;
	}
}

$closed = $zip->close();
remove_dir( $build );
if ( ! $closed ) {
	exit( "Fehler beim Schreiben des Zips.\n" );
}

printf( "%d Dateien -> %s (%d KB)\n", $count, $target, (int) round( filesize( $target ) / 1024 ) );

function copy_dir( string $from, string $to ): void {
	mkdir( $to, 0777, true );
	$items = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $from, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::SELF_FIRST );
	foreach ( $items as $item ) {
		$dest = $to . '/' . substr( $item->getPathname(), strlen( $from ) + 1 );
		$item->isDir() ? mkdir( $dest, 0777, true ) : copy( $item->getPathname(), $dest );
	}
}

function remove_dir( string $dir ): void {
	if ( ! is_dir( $dir ) ) {
		return;
	}
	$items = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $items as $item ) {
		$item->isDir() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() );
	}
	rmdir( $dir );
}
