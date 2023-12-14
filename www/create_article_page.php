<?php 
require("./templates/header.php"); 
if(!isset($_SESSION["email"])){header("Location: index.php");}
?>
<link rel="stylesheet" href="./css/style_create_article.css">
<?php
require("./templates/create_article.php");
require("./templates/footer.php");
?>