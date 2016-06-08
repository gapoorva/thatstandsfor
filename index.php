<?php
	if($_SERVER['REQUEST_METHOD'] == "GET") {
?>
<html>
	<head>
		<title>A Slack integration</title>
		<link rel="stylesheet" type="text/css" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css">
	</head>
	<body>
		<div class="container">
		  <div class="jumbotron">
		    <h1>That Stands For</h1> 
		    <p>A Slack integration that tells you what acryonms mean. Mostly used to mock people that overuse acryonms.</p> 
		    <p>More info and installation instructions <a href="http://github.com/gapoorva/thatstandsfor"> here. </a></p>
		  </div>
		</div>
	</body> 
</html>
<?php
	} else { // assuming post (from slack)

		/*
			token=gIkuvaNzQIHg97ATvDxqgjtO
			team_id=T0001
			team_domain=example
			channel_id=C2147483705
			channel_name=test
			user_id=U2147483697
			user_name=Steve
			command=/weather
			text=94070
			response_url=https://hooks.slack.com/commands/1234/5678
		*/
		header('Content-Type: application/json');

		$input_text = $_REQUEST['text'];
		$team_id = $_REQUEST['team_id'];
		$user_id = $_REQUEST['user_id'];

		$response = array('response_type' => 'in_channel');

		$servername = "localhost";
		$username = "gapoorva_1";
		$password = "mysql";
		$dbname = "gapoorva_content";

		$conn = new mysqli($servername, $username, $password, $dbname);

		// Check connection
		if ($conn->connect_error) {
		    die("Connection failed: " . $conn->connect_error);
		} 

		$args = preg_split("/[\s,]+/", $input_text);

		if ($input_text == "") { //empty input
			// choose a random acronym
		} else if (sizeof($args) == 1) { // expand acronym (GET)
			$id = strtoupper($args[0]); //ucwords()
			$user_specific_id = $user_id . $team_id . strtoupper($args[0]);

			$stmt = $conn->prepare('SELECT id, expansion FROM thatstandsfor WHERE id = ? OR id = ?');
			$stmt->bind_param('ss', $id, $user_specific_id);
			$stmt->execute();
			$stmt->bind_result($acronym, $expansion);

			$expansions = array();

			// necessary evil :(
			while($stmt->fetch()) {
				if ($acronym == $user_specific_id) {
					$response['text'] = $expansion;
					break;
				}
				$expansions[] = $expansion;
			}

			if (sizeof($expansions) && !$response['text']) { // there are some expansions for this acronym
				//pick a random one
				$response['text'] = $expansions[rand(0, sizeof($expansions)-1)];
			} else if (!$response['text']) { // "cache miss" - gotta fetch some more stuff
				// temporary solution :P
				$response['text'] = "Nothing. That stands for absolutely nothing.";
			}

		} else if (sizeof($args) > 1) { // personal definition (POST)
			$user_specific_id = $user_id . $team_id . strtoupper($args[0]);
			$user_specific_def = substr(ucwords(implode(" ", array_slice($args, 1))), 0, 250);

			$stmt = $conn->prepare('INSERT INTO thatstandsfor (id, expansion) VALUES (?, ?)');
			$stmt->bind_param('ss', $user_specific_id, $user_specific_def);
			$stmt->execute();

			$response['text'] = "OK, next time you ask what " . strtoupper($args[0]) . " stands for, I'll remind you that stands for \"" . $user_specific_def . "\"";
			$response['response_type'] = 'ephemeral';
		}
 
		echo json_encode($response);
		//$response = array('response_type' => 'in_channel', 'text' => $_REQUEST['team_id']);
		//echo json_encode($response);
		//$reqText = $_REQUEST["text"];

	}
?>
