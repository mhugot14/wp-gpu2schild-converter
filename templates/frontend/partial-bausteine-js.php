<?php
/**
 * Gemeinsame Skript-Bausteine für die Reiter. Wird in layout.php VOR dem Reiter eingebunden,
 * damit die Skripte der Reiter sie beim Ausführen schon vorfinden.
 *
 * window.mhUsc.passt( muster, wert )      – dieselbe Logik wie Service\Muster
 * window.mhUsc.bausteinfeld( el, optionen ) – Eingabe mit entfernbaren Bausteinen, synchron mit
 *                                          einem Textfeld (data-ziel) in der Textform
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<script>
window.mhUsc = window.mhUsc || ( function () {
	function regexe( muster ) {
		var teile = muster.split( ',' ).map( function ( t ) { return t.trim(); } ).filter( Boolean );
		if ( ! teile.length || teile.indexOf( '*' ) > -1 || teile.indexOf( '%' ) > -1 ) { return null; }
		return teile.map( function ( t ) {
			return new RegExp( '^' + t.replace( /[.+?^${}()|[\]\\]/g, '\\$&' ).replace( /[*%]/g, '.*' ) + '$', 'i' );
		} );
	}
	function passt( muster, wert ) {
		var re = regexe( muster );
		return re === null || re.some( function ( r ) { return r.test( String( wert ).trim() ); } );
	}
	function text( t ) { return document.createTextNode( t ); }

	/**
	 * el: .mh-usc-chipfeld mit data-ziel (Textfeld), data-trenner ("|" oder ","), data-liste (datalist),
	 *     data-was ("Fächer"/"Klassen"), optional data-schild (Bezeichnungen anzeigen).
	 * optionen: { form, schild: {Kurzform: Bezeichnung}, beiAenderung: function }
	 */
	function bausteinfeld( el, optionen ) {
		var form    = optionen.form;
		var ziel    = form.querySelector( el.dataset.ziel );
		var trenner = el.dataset.trenner;
		var eingabe = el.querySelector( 'input' );
		var dl      = document.querySelector( el.dataset.liste );
		// Die datalist dient nur als Datenquelle: Die Browser-Liste zeigt bei Kurzform + Bezeichnung
		// nur die Bezeichnung und filtert nicht steuerbar. Daher eine eigene Auswahlliste.
		var eintraege = dl ? Array.prototype.map.call( dl.options, function ( o ) { return { wert: o.value, label: o.label && o.label !== o.value ? o.label : '' }; } ) : [];
		var werte     = eintraege.map( function ( e ) { return e.wert; } );
		eingabe.removeAttribute( 'list' );
		eingabe.setAttribute( 'role', 'combobox' );
		eingabe.setAttribute( 'aria-autocomplete', 'list' );
		eingabe.setAttribute( 'aria-expanded', 'false' );
		var liste_el = document.createElement( 'ul' );
		liste_el.className = 'mh-usc-auswahl';
		liste_el.setAttribute( 'role', 'listbox' );
		liste_el.id = 'usc-auswahl-' + Math.random().toString( 36 ).slice( 2 );
		eingabe.setAttribute( 'aria-controls', liste_el.id );
		el.appendChild( liste_el );
		var treffer_akt = [];
		var aktiv       = -1;
		var was     = el.dataset.was || '';
		var schild  = optionen.schild || {};
		var schildK = Object.keys( schild );
		var vorschl = form.querySelector( '.mh-usc-vorschlaege[data-fuer="' + el.dataset.ziel + '"]' );
		var self    = { max: 0, ziel: ziel, eingabe: eingabe };

		self.liste = function () {
			return ziel.value.split( trenner ).map( function ( t ) { return t.trim(); } ).filter( Boolean );
		};
		function setze( liste ) {
			ziel.value = liste.join( '|' === trenner ? '|' : ', ' );
			self.zeichne();
			optionen.beiAenderung && optionen.beiAenderung();
		}
		/** Höchstzahl setzen (0 = beliebig), überzählige Bausteine entfernen, Änderung immer melden. */
		self.begrenzen = function ( max ) {
			self.max = max;
			var liste = self.liste();
			if ( max && liste.length > max ) {
				setze( liste.slice( 0, max ) );
			} else {
				self.zeichne();
				optionen.beiAenderung && optionen.beiAenderung();
			}
		};
		self.hinzu = function ( wert ) {
			wert = String( wert ).trim().replace( /[|,;]/g, '' );
			if ( ! wert ) { return; }
			var liste = self.liste();
			if ( liste.some( function ( w ) { return w.toLowerCase() === wert.toLowerCase(); } ) ) { eingabe.value = ''; return; }
			if ( self.max && liste.length >= self.max ) { liste = liste.slice( 0, self.max - 1 ); }
			liste.push( wert );
			eingabe.value = '';
			setze( liste );
		};
		self.zeichne = function () {
			el.querySelectorAll( '.mh-usc-baustein' ).forEach( function ( b ) { b.remove(); } );
			var liste = self.liste();
			liste.forEach( function ( wert, i ) {
				var b = document.createElement( 'span' );
				b.className = 'mh-usc-baustein';
				var m = wert.match( /^([^*%]+)[*%]$/ );
				if ( m ) {
					var anzahl = werte.filter( function ( w ) { return passt( wert, w ); } ).length;
					b.appendChild( text( m[1] + '… ' ) );
					var s = document.createElement( 'small' );
					s.textContent = 'alle ' + was + ', die mit „' + m[1] + '“ beginnen' + ( werte.length ? ' (' + anzahl + ')' : '' );
					b.appendChild( s );
				} else {
					b.appendChild( text( wert ) );
					if ( el.dataset.schild && schildK.length ) {
						var k = schildK.filter( function ( x ) { return x.toLowerCase() === wert.toLowerCase(); } )[0];
						if ( k !== undefined ) {
							if ( schild[ k ] ) { var s2 = document.createElement( 'small' ); s2.textContent = schild[ k ]; b.appendChild( s2 ); }
						} else {
							b.classList.add( 'is-fremd' ); b.title = 'Nicht in der Schild-Fächerliste';
						}
					}
				}
				var x = document.createElement( 'button' );
				x.type = 'button'; x.textContent = '×'; x.setAttribute( 'aria-label', wert + ' entfernen' );
				x.addEventListener( 'click', function () { var l = self.liste(); l.splice( i, 1 ); setze( l ); eingabe.focus(); } );
				b.appendChild( x );
				el.insertBefore( b, eingabe );
			} );
			el.classList.toggle( 'is-voll', !! self.max && liste.length >= self.max );
		};
		function vorschlagen() {
			if ( ! vorschl ) { return; }
			vorschl.innerHTML = '';
			var t = eingabe.value.trim();
			if ( ! t || ! werte.length ) { return; }
			var treffer = werte.filter( function ( w ) { return w.toLowerCase().indexOf( t.toLowerCase() ) === 0; } );
			if ( treffer.length < 2 ) { return; }
			var btn = document.createElement( 'button' );
			btn.type = 'button';
			btn.textContent = '+ alle ' + treffer.length + ' ' + was + ', die mit „' + t + '“ beginnen';
			btn.addEventListener( 'click', function () { self.hinzu( t + '*' ); vorschlagen(); } );
			vorschl.appendChild( btn );
		}

		// --- Auswahlliste: sucht in Kurzform und Bezeichnung -------------------------
		function suchen( t ) {
			t = t.trim().toLowerCase();
			if ( ! t ) { return []; }
			var gewaehlt = self.liste().map( function ( w ) { return w.toLowerCase(); } );
			var rang = function ( e ) {
				var w = e.wert.toLowerCase();
				if ( w === t ) { return 0; }
				if ( w.indexOf( t ) === 0 ) { return 1; }
				if ( e.label.toLowerCase().indexOf( t ) === 0 ) { return 2; }
				if ( w.indexOf( t ) > -1 ) { return 3; }
				if ( e.label.toLowerCase().indexOf( t ) > -1 ) { return 4; }
				return 9;
			};
			return eintraege
				.filter( function ( e ) { return gewaehlt.indexOf( e.wert.toLowerCase() ) === -1 && rang( e ) < 9; } )
				.sort( function ( a, b ) { return rang( a ) - rang( b ) || a.wert.localeCompare( b.wert, 'de' ); } )
				.slice( 0, 12 );
		}
		function markiere( teil, t ) {
			var frag = document.createDocumentFragment();
			var i = teil.toLowerCase().indexOf( t.toLowerCase() );
			if ( ! t || i < 0 ) { frag.appendChild( text( teil ) ); return frag; }
			frag.appendChild( text( teil.slice( 0, i ) ) );
			var m = document.createElement( 'mark' ); m.textContent = teil.slice( i, i + t.length ); frag.appendChild( m );
			frag.appendChild( text( teil.slice( i + t.length ) ) );
			return frag;
		}
		function zeigeAuswahl() {
			var t = eingabe.value.trim();
			treffer_akt = suchen( t );
			// Vorauswahl nur bei eindeutigem Treffer – sonst übernimmt Enter den getippten Text.
			aktiv = treffer_akt.length && ( treffer_akt[0].wert.toLowerCase() === t.toLowerCase() || 1 === treffer_akt.length ) ? 0 : -1;
			liste_el.innerHTML = '';
			treffer_akt.forEach( function ( e, i ) {
				var li = document.createElement( 'li' );
				li.setAttribute( 'role', 'option' );
				li.id = liste_el.id + '-' + i;
				var b = document.createElement( 'b' ); b.appendChild( markiere( e.wert, t ) ); li.appendChild( b );
				if ( e.label ) { var s = document.createElement( 'span' ); s.appendChild( markiere( e.label, t ) ); li.appendChild( s ); }
				// mousedown statt click: sonst verliert das Eingabefeld vorher den Fokus.
				li.addEventListener( 'mousedown', function ( ev ) { ev.preventDefault(); waehle( i ); } );
				liste_el.appendChild( li );
			} );
			markiereAktiv();
		}
		function markiereAktiv() {
			Array.prototype.forEach.call( liste_el.children, function ( li, i ) { li.classList.toggle( 'is-aktiv', i === aktiv ); li.setAttribute( 'aria-selected', i === aktiv ? 'true' : 'false' ); } );
			liste_el.classList.toggle( 'is-offen', treffer_akt.length > 0 );
			eingabe.setAttribute( 'aria-expanded', treffer_akt.length ? 'true' : 'false' );
			if ( aktiv > -1 ) { eingabe.setAttribute( 'aria-activedescendant', liste_el.id + '-' + aktiv ); } else { eingabe.removeAttribute( 'aria-activedescendant' ); }
		}
		function schliesse() { treffer_akt = []; aktiv = -1; liste_el.innerHTML = ''; markiereAktiv(); }
		function waehle( i ) { var e = treffer_akt[ i ]; schliesse(); self.hinzu( e.wert ); vorschlagen(); eingabe.focus(); }
		/** Getippten Text auflösen: exakte Kurzform (Schreibweise aus der Liste) oder eindeutiger Treffer. */
		function aufloesen( t ) {
			var exakt = eintraege.filter( function ( e ) { return e.wert.toLowerCase() === t.trim().toLowerCase(); } )[0];
			if ( exakt ) { return exakt.wert; }
			var s = suchen( t );
			return 1 === s.length && s[0].label ? s[0].wert : t;
		}

		eingabe.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'ArrowDown' && treffer_akt.length ) {
				e.preventDefault(); aktiv = ( aktiv + 1 ) % treffer_akt.length; markiereAktiv();
			} else if ( e.key === 'ArrowUp' && treffer_akt.length ) {
				e.preventDefault(); aktiv = aktiv <= 0 ? treffer_akt.length - 1 : aktiv - 1; markiereAktiv();
			} else if ( e.key === 'Escape' && treffer_akt.length ) {
				e.preventDefault(); schliesse();
			} else if ( e.key === 'Enter' && aktiv > -1 ) {
				e.preventDefault(); waehle( aktiv );
			} else if ( ( e.key === 'Enter' || e.key === ',' || e.key === '|' || e.key === ';' ) && eingabe.value.trim() ) {
				e.preventDefault(); self.hinzu( aufloesen( eingabe.value ) ); schliesse(); vorschlagen();
			} else if ( e.key === 'Enter' ) {
				e.preventDefault();
			} else if ( e.key === 'Backspace' && ! eingabe.value ) {
				var l = self.liste(); l.pop(); setze( l );
			}
		} );
		eingabe.addEventListener( 'input', function () { zeigeAuswahl(); vorschlagen(); } );
		eingabe.addEventListener( 'focus', function () { if ( eingabe.value.trim() ) { zeigeAuswahl(); } } );
		eingabe.addEventListener( 'blur', function () {
			schliesse();
			if ( eingabe.value.trim() ) { self.hinzu( aufloesen( eingabe.value ) ); }
		} );
		el.addEventListener( 'click', function ( e ) { if ( e.target === el ) { eingabe.focus(); } } );
		ziel.addEventListener( 'input', function () { self.zeichne(); optionen.beiAenderung && optionen.beiAenderung(); } );
		self.zeichne();
		return self;
	}

	/** „A, B und C“; Platzhalter als „alle mit „X“ beginnenden“. */
	function aufzaehlung( liste, was ) {
		liste = liste.map( function ( w ) { var m = w.match( /^([^*%]+)[*%]$/ ); return m ? 'alle ' + ( was || '' ) + ' mit „' + m[1] + '“' : w; } );
		return liste.length < 2 ? ( liste[0] || '' ) : liste.slice( 0, -1 ).join( ', ' ) + ' und ' + liste[ liste.length - 1 ];
	}

	/**
	 * Radio-Karten abwählbar machen: Ein zweiter Klick auf die gewählte Karte hebt die Auswahl auf.
	 * Ob sie vorher gewählt war, wird beim Drücken gemerkt – beim click ist sie schon gewählt.
	 */
	function abwaehlbar( form, name, beiAenderung ) {
		form.querySelectorAll( 'input[type=radio][name="' + name + '"]' ).forEach( function ( r ) {
			var karte = r.closest( 'label' ) || r;
			karte.addEventListener( 'pointerdown', function () { r.dataset.warGewaehlt = r.checked ? '1' : ''; } );
			r.addEventListener( 'keydown', function ( e ) { if ( e.key === ' ' ) { r.dataset.warGewaehlt = r.checked ? '1' : ''; } } );
			r.addEventListener( 'click', function () {
				if ( r.dataset.warGewaehlt === '1' ) {
					r.checked = false;
					beiAenderung();
				}
				r.dataset.warGewaehlt = '';
			} );
		} );
	}

	return { passt: passt, bausteinfeld: bausteinfeld, aufzaehlung: aufzaehlung, abwaehlbar: abwaehlbar, text: text };
} )();
</script>
