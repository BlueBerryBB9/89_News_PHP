<section>
    <form method="post">
        <h1>Créer un article</h1>
        <label for="title" class="form-label">Titre</label>
        <input type="text" class="form-control" id="title" name="title">
        <label for="content" class="form-label">Contenu</label>
        <textarea rows="5" type="text" class="form-control" id="content" name="content"></textarea>
        <label for="category" class="form-label">Catégorie</label>
        <select class="form-control" id="category" name="category">
            <option value="news">News</option>
            <option value="work">Work</option>
            <option value="team">Team</option>
        </select>
        <button type="submit" class="btn btn-primary">Submit</button>
    </form>
    <?php
    // var_dump($_GET);
    $erreur = [];
    $message = [];
    // test prenom
    if (isset($_POST['title']) && !empty($_POST['title']) && isset($_POST['content']) && !empty($_POST['content'])) {
        array_push($message, 'ok !');
    } else {
        array_push($erreur, 'Infos non valides');
    }
    // var_dump($message);
    // var_dump($erreur);
    
    if ($erreur == []) {
        require("./inc/db.php");
        $request = $pdo->prepare("INSERT INTO article (title, content, date_pub, author, category) VALUES (?,?,?,?,?);");
        $request->execute([$_POST['title'], $_POST['content'], date('Y-m-d'), $_SESSION['id'], $_POST["category"]]);
        header("Location: ./index.php");
        exit();
    }
    ?>

    <ul>
        <?php
        foreach ($message as $value) {
            echo "<li>" . $value . "</li>";
        }
        ?>
    </ul>
    <ul>
        <?php
        foreach ($erreur as $value) {
            echo "<li>" . $value . "</li>";
        }
        ?>
    </ul>
</section>