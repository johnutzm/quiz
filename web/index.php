<?php
require_once __DIR__.'/../vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;

$app = new Silex\Application();

$app['db'] = new PDO('sqlite:'.__DIR__.'/../data/db.sqlite3');
$app['db']->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$st = $app['db']->query('SELECT COUNT(*) FROM sqlite_master WHERE name = \'sessions\' OR name = \'sessions_answers\'');
if ($st->fetchColumn() != 2) {
	$app['db']->exec(file_get_contents(__DIR__.'/../dev/schema.sql'));
}

$app['questions'] = simplexml_load_file(__DIR__.'/../data/questions.xml');

$app['debug'] = true;

$app->register(new Silex\Provider\TwigServiceProvider(), array(
	'twig.path' => __DIR__.'/../views',
));

$app->before(function () use ($app) {
	$app['twig']->addGlobal('layout', null);
	$app['twig']->addGlobal('layout', $app['twig']->loadTemplate('layout.twig'));
});

session_start();

$app->match('/', function () use ($app) {
	return $app['twig']->render('index.twig');
});

$app->get('/', function() use($app) {
	return $app['twig']->render('index.twig');
});
$app->post('/start', function(Request $request) use($app) {
	session_regenerate_id();
	/* @var $db PDO */
	$db = $app['db'];
	/* @var $st PDOStatement */
	$st = $db->prepare('INSERT INTO sessions
		(session_id, name, email, startdate)
		VALUES (?, ?, ?, ?)
	');
	$st->execute(array(
		session_id(),
		$request->get('name'),
		$request->get('email'),
		date('Y-m-d H:i:s'),
	));
	$_SESSION['quiz'] = array(
		'session_id' => $db->lastInsertId(),
		'name' => $request->get('name'),
		'email' => $request->get('email'),
	);
	return $app->redirect('/question/1');
});

$app->get('/question/{id}', function($id) use($app) {
	if ($question = $app['questions']->question[$id-1]) {

		/* @var $db PDO */
		$db = $app['db'];

		$stc = $db->prepare('SELECT id FROM sessions_answers WHERE session_id  = ? AND question_id = ?');
		$stc->execute(array($_SESSION['quiz']['session_id'], $id,));
		if (!$stc->fetchColumn()) {

			$st = $db->prepare('INSERT INTO sessions_answers
				(session_id, question_id, date_start)
				VALUES (?, ?, ?)
			');
			$st->execute(array(
				$_SESSION['quiz']['session_id'],
				$id,
				date('Y-m-d H:i:s'),
			));
		}

		$answers = array();
		for($i = 0; $i < $question->answers->answer->count(); $i++) {
			$answers[] = $question->answers->answer[$i];
		}

		return $app['twig']->render('question.twig', array(
			'text' => (string)$question->text,
			'type' => (string)$question->attributes()->type,
			'answers' => $answers,
			'name' => $_SESSION['quiz']['name'],
			'email' => $_SESSION['quiz']['email'],
		));
	}
});

$app->post('/question/{id}', function($id, Request $request) use($app) {

	if($question = $app['questions']->question[$id-1]) {
		
		$correctAnswers = [];
		for ($i = 0; $i < $question->answers->answer->count(); $i++) {
			if ($question->answers->answer[$i]->attributes()->correct) {
				$correctAnswers[] = $i;
			}
		}
	}

	$answer = (array)$request->get('answer');
	$correct = $correctAnswers == $answer;

	/* @var $db PDO */
	$db = $app['db'];
	$st = $db->prepare('UPDATE sessions_answers SET
		date_end = ?,
		answer = ?,
		correct = ?
		WHERE session_id = ? AND question_id = ?
	');
	$st->execute(array(
		date('Y-m-d H:i:s'),
		join(',', $answer),
		(int)(bool)$correct,
		$_SESSION['quiz']['session_id'],
		$id,
	));

	$url = $app['questions']->question->count() <= $id ? '/finish' : '/question/' . ($id+1);

	return $app->redirect($url);

});

$app->get('/finish', function() use($app) {
	/* @var $db PDO */
	$db = $app['db'];
	$st = $db->prepare('SELECT COUNT(*) FROM sessions_answers WHERE session_id = ? AND correct = 1');
	$st->execute(array($_SESSION['quiz']['session_id']));
	$correctCount = $st->fetchColumn();
	$totalCount  = $app['questions']->question->count();
	return $app['twig']->render('finish.twig', array(
		'correctCount' => $correctCount,
		'totalCount' => $totalCount,
	));
});

$app->get('/results', function() use($app) {
	/* @var $db PDO */
	$db = $app['db'];
	$st = $db->query('SELECT
		  sessions.name,
		  sessions.email,
		  sessions.startdate,
		  (SELECT
		  COUNT(id)
		   FROM sessions_answers
		   WHERE sessions_answers.session_id = sessions.id AND correct) AS correct
		FROM sessions
		ORDER BY sessions.startdate
		  DESC
		');
	$data = $st->fetchAll(PDO::FETCH_ASSOC);

	return $app['twig']->render('results.twig', array('data' => $data));
});

$app->run();