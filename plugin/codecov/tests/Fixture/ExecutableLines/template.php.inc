<?php function render(array $rows): void { ?>
<ul>
<?php foreach ($rows as $row): ?>
    <li><?= $row ?></li>
<?php endforeach ?>
</ul>
<?php } ?>
