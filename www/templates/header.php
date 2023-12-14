<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="./images/logo_ecole89.jpg" type="image/x-icon">
    <title>Ecole89 - Student News</title>
    <link rel="stylesheet" href="./css/reboot.css">
</head>
<body>

    <main>
        <section>
            <header>
                <h1>ECOLE89 <br> STUDENTS <br> NEWS</h1>
            </header>
            <div>
                <h2><strong>Latest</strong> news <b>from our students</b></h2>
                <p>Lorem ipsum dolor sit, amet consectetur adipisicing elit. Expedita repudiandae impedit aliquam beatae saepe dignissimos ipsa delectus necessitatibus? Illum doloribus, dignissimos unde ea voluptate asperiores perferendis natus iusto molestiae eum.</p>
            </div>
            <footer> 
                <a href="./index.php">Plan Du Site</a>
                <a href="legal_page.php">Mentions Légales</a>
                <a href="contact_page.php">Contact</a>
                <?php
                if (!isset($_SESSION["email"])):
                ?>
                <a href="register_page.php">Inscription</a>
                <a href="login_page.php">Connection</a>
                <?php
                else:
                ?>
                <a href="logout.php">Se déconnecter</a>
                <a href="create_article_page.php">Créer un article</a>
                <?php
                endif;
                ?>
            </footer>
        </section>