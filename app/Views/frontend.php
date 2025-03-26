<?php

use App\Libraries\HyperHooks;
?>
<html>

<head>
    <meta name="viewport" content="initial-scale=1, width=device-width" />
    <?= HyperHooks::getInstance()->trigger('frontend:head') ?>
</head>

<body>
    <?= HyperHooks::getInstance()->trigger('frontend:header') ?>
    <?= HyperHooks::getInstance()->trigger('frontend:body') ?>
    <?= HyperHooks::getInstance()->trigger('frontend:footer') ?>
</body>

</html>