<?php

$GLOBALS['puce'] = '- ';

// A partir d'un titre renvoyer titre, auteur, source
function retitrage($titre, $quoi='titre') {
  static $r = array();
  if (!isset($r[$c = md5($titre)])) {
    $r[$c] = array();
    if (preg_match(',^(.*)\s+\(([^(]+)\)$,UimsS', trim($titre), $regs)
    AND !preg_match(',^\d+$,S', $regs[2])) {
      $titre = $regs[1];
      $r[$c]['source'] = $regs[2];
    }
    if (preg_match('/^(.*),\s+(?:by|par)\s+(.+)$/UimsS', $titre, $regs)) {
      $titre = $regs[1];
      $r[$c]['auteurs'] = $regs[2];
    }
    $r[$c]['titre'] = $titre;
  }

  return $r[$c][$quoi];
}

// Renvoie 14h12 si c'est le meme jour, et 19/02 si c'est un autre jour
function datehm($date) {
	$u = date('U', strtotime($date));
	$n = date('U');
	return ($n-$u < 24*3600)
		? date('H\hi', $u)
		: date('d/m', $u);
}

// pour que les crayons fonctionnent chez les redacteurs
function autoriser_modifier_article($quoi, $id, $qui) {
	return in_array($qui['statut'], array('0minirezo', '1comite'));
}

// les tags arrivent sous forme d'une liste de mots seapares par des virgules
// on remet les bons mots quand il s'agit de mots-cles connus, et on passe 
// chaque mot en gras
function embellir_tags($tags, $dest='html') {
	static $alias;
	if (!isset($alias)) {
		$s = spip_query("SELECT descriptif,titre FROM spip_mots");
		while ($t = sql_fetch($s))
			if (strlen($t['titre']))
				$alias[trim($t['descriptif'])] = $t['titre'];
	}

	$mots = array();
	foreach (array_filter(array_map('trim', explode(',', $tags))) as $tag) {
		if (isset($alias[$tag]))
			$tag = $alias[$tag];
		$tag = supprimer_numero($tag);

		if (!preg_match(',^(affichage une|actualit.*|communiqu.*de presse)$,imsS', $tag)) {

			if ($dest == 'html') {
				$tag = "<b>$tag</b>";
				$join = ", ";
			} elseif ($dest == 'dc') {
				$tag = "<dc:subject>".texte_backend($tag)."</dc:subject>";
				$join = "\n";
			} elseif ($dest == 'reltag') {
				$enc = strtolower(translitteration($tag));
				if (strstr($enc, ' '))
					$enc = '"'.$enc.'"';
				$url = url_absolue('/themes/'.urlencode($enc));
				$tag = "<a rel='tag' href='$url'>$tag</a>";
				$join = ", ";
			}

			$mots[] = $tag;

		}
	}

	if ($mots)
		return join($join, $mots);
}

// Mettre a jour la popularite d'un mot-cle (cf. mot-fulltext.html)
function majpopmot($id_mot, $pop) {
	if ($id_mot=intval($id_mot))
		spip_query($q = "UPDATE spip_mots
			SET popularite=".sql_quote($pop)."
			WHERE id_mot=$id_mot");
#	return $q;
}

// Renvoie les n mots-cles les plus populaires
function mots_populaires($n=25) {
	/* $f = "select sum(articles.popularite) as pop, m.id_mot AS id_mot, mots.titre as titre from spip_articles as articles right join spip_mots_articles as m ON articles.id_article = m.id_article, spip_mots AS mots WHERE m.id_mot=mots.id_mot group by id_mot order by pop desc limit 0,".intval($n); */
	// Utilise le plugin "Popularite"
	$f = "SELECT popularite AS pop, id_mot, titre
	FROM spip_mots
	ORDER BY popularite DESC
	LIMIT 0,".intval($n);
	if ($s = spip_query($f)) {
		$a =array();
		while ($t = sql_fetch($s))
			$a[] = $t;
	}
	return $a;
}
function random_sort($a,$b) {
	return rand(0,1)
		? 1
		: -1;
}
function nuage_tags($ignore, $n=25, $random=1) {
	if (is_array($a = mots_populaires($n))) {
		$maxpop = $a[0]['pop'];
		$minpop = $a[count($a)-1]['pop'];
		$l = array();
		foreach($a as $mot) {
			$score = ($mot['pop']-$minpop)/($maxpop-$minpop+1); # entre 0 et 1
			$score = pow($score,0.5); # lissage
			$s = 10+ceil(10*$score);
			$t = str_replace(' ', '&nbsp;', typo($mot['titre']));
			$l[] = "<a href='".generer_url_entite($mot['id_mot'],'mot')."'
			style='font-size: ${s}px;'>$t</a>";
		}

		if ($random)
			usort($l, 'random_sort');

		return '<a href="/bestof">La s&#233;lection du Portail</a> '. join("\n", $l);
	}
}

// Le titre des rubriques, version rapide : [(#ID_RUBRIQUE|titre_rub)]
function titre_rub($id_rubrique) {
	static $brut, $typo = array();

	if (isset($typo[$id_rubrique]))
		return $typo[$id_rubrique];

	if (!isset($brut)) {
		include_spip('base/abstract_sql');
		foreach(sql_allfetsel(array('titre','id_rubrique', 'lang'), array('spip_rubriques')) as $t)
			$brut[$t['id_rubrique']] = array($t['titre'], $t['lang']);
	}

	if (!isset($typo[$id_rubrique])) {
		lang_select($brut[$id_rubrique][1]);
		$typo[$id_rubrique] = typo($brut[$id_rubrique][0]);
		lang_select();
	}

	return $typo[$id_rubrique];
}

// Les mots des articles, version rapide : [(#ID_ARTICLE|mots_article)]
function mots_article($id_article, $wrap='%s', $sep=', ') {
	static $liens, $titres;

	if (!isset($liens)) {
		include_spip('base/abstract_sql');
		foreach(sql_allfetsel(array('id_mot','id_article'), array('spip_mots_articles')) as $t)
			$liens[intval($t['id_article'])][] = intval($t['id_mot']);
		foreach(sql_allfetsel(array('id_mot','titre'), array('spip_mots')) as $t)
			$titres[intval($t['id_mot'])] = $t['titre'];
	}

	if (!isset($liens[$id_article]))
		return '';

	$mots = array();
	foreach ($liens[$id_article] as $id_mot)
		$mots[] = sprintf($wrap, $titres[$id_mot]);  # on ne fait pas typo()

	return join($sep, $mots);
}

function microcache($id, $fond, $calcul=false) {
	$cle = "$fond-$id";
	$ttl = 60*60;
	if ($calcul
	OR in_array($_GET['var_mode'], array('recalcul', 'debug'))
	OR !($contenu = cache_get($cle))) {
		$contenu = recuperer_fond($fond, array('id'=>$id));
		cache_set($cle, $contenu, $ttl);
	}
	return $contenu;
}

// separer les tags qui peuvent etre de trois sortes :
// <a rel='tag'>XXX</a>
// <a rel='enclosure'>XXX</a>
// un mot, un autre
function rezo_tags($tags) {
	$mots = array();
	foreach (extraire_balises($tags, 'a') as $t) {
		$tags = str_replace($t, '', $tags);
		if (extraire_attribut($t, 'rel') == 'enclosure'
		AND preg_match(',mp3,', extraire_attribut($t, 'href')))
			$mots[] = 'Audio';
		if (extraire_attribut($t, 'rel') == 'tag')
			$mots[] = supprimer_tags($t);
	}
	foreach(explode(',', $tags) as $m)
		if ($m = trim($m))
			$mots[] = $m;

	return join(', ', array_unique($mots));
}

// fonctions pour le plugin core/sites
if (!function_exists('balise_img')){
function balise_img($img,$alt="",$class="") { return tag_img($img,$alt,$class); }
}


### pour vieux squelettes (lautre, vieux)
## retourne la date de comparaison au format MySQL
## a utiliser comme critere {date>(#REM|limite_age{850})}
function limite_age($maintenant, $jours) {
	if ($maintenant)
		$time = strtotime($maintenant);
	else
		$time = time();
	return date('Y-m-d', $time-$jours*24*3600);
}

## fonction vocabulaire()
##include_spip('categorize');


function recuperer_page_cache($url, $delai=3601) {
	include_spip('inc/distant');
	if ($W = cache_me(null, $delai)) return $W;
	return recuperer_page($url, true);
}

// pour la page /agenda
function filtrer_agenda_demosphere ($agenda) {
	$agenda = str_replace('Grironde','Gironde', $agenda);


	include_spip('inc/charsets');

	$dems = explode('<h3>', $agenda);

	foreach ($dems as $k=>&$demo) {
		preg_match(',^\s*<a.*?>\s*([^<\s]*),', $demo, $r);

		if ($lieu = strtolower(trim($r[1]))) {
			$id = translitteration($lieu);
			$ancres[] = "<a href='#$id'>$lieu</a>";
		} else
			$id = "first";

		$demo = "<h3 id='$id'>".$demo;
		
		if (preg_match_all('/(<li class="[ \w]*">)([ \d]+ \S+) *-*/', $demo, $regs, PREG_SET_ORDER))
		foreach($regs as $r) {
			$date = str_replace(
				array('fev', 'avr', 'mai', 'aou'),
				array('feb', 'apr', 'may', 'aug'),
				trim(translitteration($r[2])));
			if ($date = strtotime($date)) {
				$date = date('Y-m-d', $date);
				$date = nom_jour($date).' '.affdate_court($date);
			} else
				$date = $r[2];

			if ($date = unique($date, 'demo'.$k))
				$date = '<h5>'. $date . '</h5>';

			$demo = preg_replace('/'.preg_quote($r[0]).'/', $r[1].$date, $demo,1);
		}
		$demo = preg_replace(',<(/?)li\b,', '<\1div', $demo);
		$demo = preg_replace(',<(/?)ul\b,', '<\1div class="articles"', $demo);
		$demo = preg_replace(',<a\b,', '<a rel="bookmark"', $demo);

	}


	return
		"<div class='ancres'>".join(' - ',$ancres)."</div>\n"
		.
		typo(join("\n",$dems));
}