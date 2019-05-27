<?php
if (isset($_POST["email"]) && isset($_POST["password"])) {
    $loginResult = json_decode(file_get_contents('https://apps.wikitree.com/api.php?action=login&email=' . $_POST["email"] . '&password=' . $_POST["password"]), true);
    setcookie("wikitree_wtb_UserName", $loginResult["login"]["username"], time() + (86400 * 30), "/");
    $userName = $loginResult["login"]["username"];
} else if (isset($_COOKIE["wikitree_wtb_UserName"])) {
    $userName = $_COOKIE["wikitree_wtb_UserName"];
}
?>
<html>
<head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<style>
.content {
	text-align: center;
}
</style>
<title>Descendant Tree</title>
<meta name="viewport" content="initial-scale=1, maximum-scale=1">
</head>
<body>
<?php

if (isset($_GET['nologin']) && $_GET['nologin'] == '1') {
    $noLogin = $_GET['nologin'];
} else $noLogin = FALSE;

if (isset($userName) || $noLogin) {
    echo "<div class=\"content\">\n<br>\n";
    if (isset($userName)) {
        echo "Logged in as " . $userName . ".<br>\n<br>\n";
    }
	echo "<form action=\"draw-tree.php\" method=\"post\">\n";
	echo "Show all paths of descent from person A to person B:<br>\n";
	echo "Person A: <input type=\"text\" name=\"target\"><br>\n";
    echo "Person B: <input type=\"text\" name=\"base\"><br>\n";
    echo "<input type=\"checkbox\" id=\"debug\" name=\"debug\"><label for=\"debug\">Show intermediate processing steps</label><br>";
	echo "<input type=\"submit\">\n";
    echo "</form>\n</div>\n";
} else {
	echo "<div class=\"content\">\n<br>\n";
    echo "<form action=\"#\" method=\"post\">\n";
    if (isset($loginResult)) {
        echo "Login failed. Please try again.<br><br>\n";
    }
	echo "Login to apps.wikitree.com with the same information that you use for WikiTree.<br><br>\n";
	echo "email: <input type=\"text\" name=\"email\"><br>\n";
	echo "password: <input type=\"password\" name=\"password\"><br>\n";
	echo "<input type=\"submit\" value=\"Login\">\n";
    echo "</form>\n";
    echo "Click <a href=\"?nologin=1\">here</a> if you want to use the app without logging in.\n";
    echo "</div>\n";
}
?>
</body>
</html>