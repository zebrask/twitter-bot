<?php
// DOC
// https://developer.twitter.com/en/docs/tweets/data-dictionary/overview/intro-to-tweet-json
//define('PROD', false);
function before()
{
	$s = [ 'J en connais un à qui ça plairait', 'Whaou je participe avec plaisir', 'Je mentionne', 'ça va ? ', 'salut', 'coucou', 'hey', 'hello', 'bonjour', 'Respect à', 'Et voilà' ];
	return $s[array_rand($s)];
}

function after()
{
  $smilies = array('cool et merci', 'GG', 'a+'  ,':-|' ,':-o'  ,':-O' ,':o' ,':O' ,';)'  ,';-)' ,':p'  ,':-p' ,':P'  ,':-P' ,':D' ,':-D' ,'8)' ,'8-)' ,':)'  ,':-)', 'Merci pour ce magnifique cadeau !', 'Merci', ' merci beaucoup !!' );
  return $smilies[array_rand($smilies)];
}
  
//debug 
function elog($m)
{
	if (! PROD) echo "<font clor=gray>$m</font><br>\n";
}

//charges les identifiants tweeter
require_once("i.php");

//charge la librairie tweeter
require 'twitteroauth/autoload.php';
use Abraham\TwitterOAuth\TwitterOAuth;

//se connecte à tweeter
$connection = new TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET, ACCESS_TOKEN, ACCESS_TOKEN_SECRET);
	
//lit le dernier id
if (! $last_id = file_get_contents(FILE)) $last_id = 0;
elog("last : $last_id<br>");
$nb_concours = 0;
//cherche #concours
$results = $connection->get('search/tweets', [ 'q' => SRCH_t, 'lang' => 'fr', 'result_type' => 'popular', 'count' => NB_TWEET_RAMENER, 'include_entities' => false, 'since_id' => $last_id]);

foreach ($results->statuses as $tweet) 
{
	$nb_concours++;
	$texte = $tweet->text;
	elog("Tweet: " . $texte);
	
	//1-favorise le tweet
	if (PROD) $connection->post('favorites/create', [ 'id' => $tweet->id_str ]);
	elog('favori: <a target=_blank href=https://twitter.com/' . $tweet->user->screen_name . '/status/' . $tweet->id_str . '>' . $tweet->id_str . '</a>');
	
	//2-rt le tweet
	if (PROD) $connection->post('statuses/retweet', [ 'id' => $tweet->id_str]);
	elog('retweet: ' . $tweet->id_str);
	
	//3-prépare un commentaire avec des mentions @XX @YY @ZZ si la mention est nécessaire uniquement
	if (strpos(strtolower($texte), 'partage') > 1 || strpos(strtolower($texte), 'identifie') > 1 || strpos(strtolower($texte), 'mention') > 1 || strpos(strtolower($texte), 'répond') > 1) $mentionner = true;
	else $mentionner = false;
	
	if ($mentionner)
	{
		$users = $connection->get('followers/list', [ 'user_id' => $tweet->user->id_str, 'count' => AMIS_NB_FOLLOWER ]);
		$nom_commentaire = null;
		foreach ($users->users as $user) $nom_commentaire .= '@' . $user->screen_name . ' ';
	}
		
	//4-follow le compte
	if (PROD) $connection->post('friendships/create', [ 'screen_name' => $tweet->user->screen_name, 'follow' => 'false']);
	elog('follow: ' . $tweet->user->screen_name);
	
	//5-follow les comptes associés dans le tweet
	//raz initiales
	$compter_nom = false;
	$compter_hashtag = false;
	$nom = null;
	$hashtag = null;
	//parcours des tweets retenus
	for ($j=0; $j<strlen($texte); $j++)
	{
		$lettre = substr($texte, $j,1);
		//détecte un nom d'utilisateur
		if ($lettre == '@')
		{
				$nom = null;
				$compter_nom = true;
		}
		if ($lettre == '#')
		{
				$hashtag .= ' ';
				$compter_hashtag = true;
		}				
		
		//détecte la fin du nom
		if ($lettre ==  '.' ||  $lettre ==  ',' || $lettre ==  ' ' || ($j == strlen($texte)-1))
		{ 
				if ($compter_nom) 
				{
					elog('follow sup: ' . $nom);
					if (PROD) $connection->post('friendships/create', [ 'screen_name' => $nom, 'follow' => 'false']);
				}
				//raz
				$compter_nom = false;
				$compter_hashtag = false;
		}
		
		if ($compter_nom && $lettre != '@') $nom .= $lettre;
		if ($compter_hashtag) $hashtag .= $lettre;
		
	}
	
	//3 bis-poste un commentaire avec des mentions @XX @YY @ZZ
	if ($mentionner)
	{
		//stoppe pour 1 seconde
		sleep(1);
		$commentaire = '@' . $tweet->user->screen_name . ' ' . before() . ' '  . trim($nom_commentaire) . ' ' . after() . ' ' . trim($hashtag);
		if (PROD) $connection->post('statuses/update', ['status' => trim($commentaire), 'in_reply_to_status_id' => $tweet->id_str ]);
		elog('tweet: ' . $commentaire);
	}
	elog(" ");
	//stoppe pour 7 secondes
	sleep(7);
}
if ($nb_concours) 
{
	//stocke le dernier id lu
	if (PROD) file_put_contents(FILE, $tweet->id_str);
	elog("last : " . $tweet->id_str);
}
?>
