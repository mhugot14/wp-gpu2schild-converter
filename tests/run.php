<?php
/**
 * Testlauf ohne WordPress und ohne Fremdpakete:
 *   C:\xampp\php\php.exe tests\run.php
 *
 * Jede Datei *_Test.php enthält eine Klasse, die Test_Case erweitert; alle öffentlichen
 * Methoden mit dem Präfix test_ werden ausgeführt. Nur erfundene Daten verwenden.
 */

declare(strict_types=1);

if ( PHP_SAPI !== 'cli' ) {
	exit( 'Nur über die Kommandozeile aufrufen.' );
}

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/Test_Case.php';

foreach ( glob( __DIR__ . '/*_Test.php' ) as $datei ) {
	require $datei;
}

$fehler = 0;
$anzahl = 0;
foreach ( get_declared_classes() as $klasse ) {
	if ( ! is_subclass_of( $klasse, Test_Case::class ) ) {
		continue;
	}
	echo $klasse, PHP_EOL;
	foreach ( get_class_methods( $klasse ) as $methode ) {
		if ( ! str_starts_with( $methode, 'test_' ) ) {
			continue;
		}
		$anzahl++;
		try {
			( new $klasse() )->$methode();
			echo '  ✓ ', $methode, PHP_EOL;
		} catch ( Throwable $e ) {
			$fehler++;
			echo '  ✗ ', $methode, PHP_EOL, '      ', $e->getMessage(), PHP_EOL;
			if ( ! $e instanceof Test_Fehler ) {
				echo '      ', $e->getFile(), ':', $e->getLine(), PHP_EOL;
			}
		}
	}
}

echo PHP_EOL, $anzahl - $fehler, ' von ', $anzahl, ' Tests bestanden.', PHP_EOL;
exit( $fehler > 0 ? 1 : 0 );
