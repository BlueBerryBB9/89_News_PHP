<section>
    <form method="post">
        <h1>Inscription</h1>
        <label for="login" class="form-label">Pseudonyme</label>
        <input type="text" class="form-control" id="login" name="login">
        <label for="exampleInputEmail1" class="form-label">Email</label>
        <input type="email" class="form-control" id="exampleInputEmail1" aria-describedby="emailHelp" name="email">
        <div id="emailHelp" class="form-text">We'll never share your email with anyone else.</div>
        <label for="exampleInputPassword1" class="form-label">Mot de passe</label>
        <input type="password" class="form-control" id="exampleInputPassword1" name="password">
        <div class="form-check">
            <input type="checkbox" class="form-check-input" id="exampleCheck1">
            <label class="form-check-label" for="exampleCheck1">Vous accepter les mentions légales</label>
        </div>
        <button type="submit">Submit</button>
    </form>
    <?php
    // var_dump($_GET);
    $erreur = [];
    $message = [];
    // test prenom
    if (isset($_POST['login']) && strlen($_POST['login']) <= 255) {
        array_push($message, 'ok pour le Pseudonyme');
    } else {
        array_push($erreur, 'Le Pseudonyme n\'est pas valide');
    }
    // test email
    if (isset($_POST['email']) && preg_match('/^[\w\-\.]+@([\w-]+\.)+[\w-]{2,4}$/', $_POST['email'])) {
        array_push($message, 'ok pour l\'email');
    } else {
        array_push($erreur, 'L\'email n\'est pas valide');
    }
    // test password
    if (isset($_POST['password']) && preg_match('/^(?=.*?[A-Z])(?=.*?[a-z])(?=.*?[0-9])(?=.*?[#?!@$%^&*-]).{8,}$/', $_POST['password'])) {
        array_push($message, 'ok pour le mdp');
    } else {
        array_push($erreur, 'Le mdp n\'est pas valide');
    }

    // var_dump($message);
// var_dump($erreur);
    
    if ($erreur == []) {
        require("./inc/db.php");
        $encrypted_password = hash('sha512', $_POST['password']);
        $request = $pdo->prepare("INSERT INTO user (login, email, password) VALUES (?,?,?);");
        $request->execute([$_POST['login'], $_POST['email'], $encrypted_password]);
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