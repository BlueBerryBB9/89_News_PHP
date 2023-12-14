<?php
    require("./inc/db.php");
    $sql="SELECT * FROM `article`;";
    $request=$pdo->query($sql);
    $postsList=$request->fetchAll(PDO::FETCH_ASSOC);
    $sql="SELECT * FROM `user`;";
    $request=$pdo->query($sql);
    $usersList=$request->fetchAll(PDO::FETCH_ASSOC);
    function fetch_user(int $id, array $usersList) {
        $count = 0;
        foreach($usersList as $users) {
            if ($users['id'] == $id) {
                return $count;
            }
            $count += 1;
        }
        return -1;
    }
?>
<section>
    <h1>Latest news</h1>
    <?php foreach($postsList as $post) { ?>
    <article>
    <?php if ($post["category"] == "news"): ?> 
            <h3 class="news">news</h3>
        <?php elseif ($post["category"] == "work"): ?> 
            <h3 class="work">work</h3>
        <?php elseif ($post["category"] == "team"): ?> 
            <h3 class="team">Team</h3>
        <?php endif; ?>
        <?php echo "<h2>".$post["title"]."</h2>"?>
        <div class="info">
            <img src="images/icon-john.png" alt="">
            <h4><strong>
            <?php
            if (fetch_user($post["author"], $usersList) == -1) {
                echo "Unknow";
            } else {
            echo $usersList[fetch_user($post["author"], $usersList)]['login'] ;
            }
            ?></strong> le <?php echo $post["date_pub"] ?></h4>
        </div>
        <p class="toudou"><?php echo $post["content"]?></p>
        <a href="./single_article_page.php?id=<?php echo $post["id"]?>">Continue reading</a>
    </article>
    <?php } ?>
</section>