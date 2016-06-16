<?php
	date_default_timezone_set('UTC');
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

handle_request:

		if ($input_text == "") { //empty input
			// throw an error
			$response['text'] = "Did you mean to ask what something stands for?\nIf so, try doing it this way:\n/what's ACRONYM_HERE";
			$response['response_type'] = "ephemeral";
		} else if (sizeof($args) == 1) { // expand acronym (GET)
			$id = strtoupper($args[0]); //ucwords()
			$user_specific_id = $user_id . $team_id . strtoupper($args[0]);

			$stmt = $conn->prepare('SELECT id, expansion, likes FROM thatstandsfor WHERE id = ? OR id = ?');
			$stmt->bind_param('ss', $id, $user_specific_id);
			$stmt->execute();
			$stmt->bind_result($acronym, $expansion, $likes);

			$expansions = array();

			// necessary evil :(
			while($stmt->fetch()) {
				if ($acronym == $user_specific_id) {
					$response['text'] = $expansion;
					break;
				}
				for($i = 0; $i < $likes; $i++) {
					$expansions[] = $expansion;
				}
			}

			if (sizeof($expansions) && !$response['text']) { // there are some expansions for this acronym and we haven't picked one yet
				//pick a random one
				$response['text'] = $expansions[rand(0, sizeof($expansions)-1)];
			} else if (!$response['text']) { // "cache miss" - gotta fetch some more stuff
				// fetch from stands4.com
				// do we still have quota?

				$quotacheck = $conn->query("SELECT COUNT(*) FROM stands4quota WHERE day='".date("d_m_Y")."'");
				$numused = $quotacheck->fetch_row()[0];

				if ($numused >= 100) {
					$response['text'] = "ThatStandsFor was super popular today! It's going to take me a little longer to define that for you. Want to try tomorrow? You can also make your own definition:\n/what's WORD My Definition For Word";
					$response['response_type'] = "ephemeral";
				} else {

					if(TRUE !== $conn->query("INSERT INTO stands4quota (day) VALUES ('".date("d_m_Y")."')")) {
						echo $conn->error;
					}
					$term = strtolower(preg_replace("/[^A-Za-z0-9]/", '', $args[0]));
					$xmlstr = file_get_contents("http://www.stands4.com/services/v2/abbr.php?uid=5167&tokenid=04QuaC8odah1Rv96&term=".$term);

					$xmlobj = simplexml_load_string($xmlstr);

					//echo $xmlstr;
					//echo "<br>";

					$sqlinsertquery = "INSERT INTO thatstandsfor (id, expansion, likes) VALUES ";
					$definitions = array();
					foreach($xmlobj->result as $r) {
						if(!in_array($r->definition, $definitions)) {
							$definitions[] = ucwords($r->definition);
						}
						if (count($definitions) > 29) { // only want as much as 30
							break;
						}
					}

					foreach ($definitions as $i => $def) {
						if ($i != 0) {
							$sqlinsertquery .= ", ";
						}
						$sqlinsertquery .= "(\"".strtoupper($term)."\",\"".ucwords($def)."\",1)";
					}
					//echo $sqlinsertquery;

					$conn->query($sqlinsertquery); // insert everything we got from stands4

					//echo "<br>inserted stuff";

					goto handle_request; // try this query again

				}

				
			}

		} else if (sizeof($args) > 1) { // personal definition (POST)
			$user_specific_id = $user_id . $team_id . strtoupper($args[0]);
			$user_specific_def = substr(ucwords(implode(" ", array_slice($args, 1))), 0, 250);

			$stmt = $conn->prepare('INSERT INTO thatstandsfor (id, expansion, likes) VALUES (?, ?, ?)');
			$one = 1;
			$stmt->bind_param('ssi', $user_specific_id, $user_specific_def, $one);
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
