<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="initial-scale=1, width=device-width" />
    <?= service('hooks')->trigger(hook('frontend.head')) ?>
</head>

<body>
    <?= service('hooks')->trigger(hook('frontend.header')) ?>
    <?= service('hooks')->trigger(hook('frontend.main')) ?>
    <?= service('hooks')->trigger(hook('frontend.footer')) ?>
</body>

</html>