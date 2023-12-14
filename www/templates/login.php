<section>
    <form method="post">
        <h1>Se connecter</h1>
        <label for="exampleInputEmail1" class="form-label">Email</label>
        <input type="email" class="form-control" id="exampleInputEmail1" aria-describedby="emailHelp" name="email">
        <label for="exampleInputPassword1" class="form-label">Mot de passe</label>
        <input type="password" class="form-control" id="exampleInputPassword1" name="password">
        <button type="submit" class="btn btn-primary">Submit</button>
    </form>
    <?php
    // var_dump($_GET);
    $erreur = [];
    $message = [];
    // test email
    
    if (
        isset($_POST['email']) && preg_match('/^[\w\-\.]+@([\w-]+\.)+[\w-]{2,4}$/', $_POST['email'])
        && isset($_POST['password']) && preg_match('/^(?=.*?[A-Z])(?=.*?[a-z])(?=.*?[0-9])(?=.*?[#?!@$%^&*-]).{8,}$/', $_POST['password'])
    ) {
        require("./inc/db.php");
        $request = $pdo->prepare(" SELECT * FROM `user` WHERE `email` = ?;");
        $request->execute([$_POST['email']]);
        $user = $request->fetch(PDO::FETCH_ASSOC);
        // var_dump($user);
        if ($user == false) {
            echo "<br>L'utilisateur ou le mot de passe est invalide";
        } else {
            if ($user["password"] == hash("sha512", $_POST['password'])) {
                echo "vous êtes connecté";
                $_SESSION["email"] = $user["email"];
                $_SESSION["role"] = $user["role"];
                $_SESSION["id"] = $user["id"];
                header("Location: index.php");
                exit();
            } else {
                echo "<br>L'utilisateur ou le mot de passe est invalide";
            }
        }
    } else {
        array_push($erreur, 'Le mdp ou l\'email n\'est pas valide');
    }

    // var_dump($message);
// var_dump($erreur);
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