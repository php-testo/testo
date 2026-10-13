<!doctype html>
<title><?= $title ?? "x" ?></title>
<?php
$items = [1, 2];
?>
<ul>
<?php foreach ($items as $i): ?>
    <li><?= $i ?></li>
<?php endforeach; ?>
</ul>
<? $short = 1; ?>
<?php
function tags(array $rows): void
{
    ?>
    <table>
    <?php foreach ($rows as $row) { ?>
        <tr><td><?= $row ?></td></tr>
    <?php } ?>
    </table>
    <?php
    echo "end";
}
?>
footer <?= 1 + 1 ?>
