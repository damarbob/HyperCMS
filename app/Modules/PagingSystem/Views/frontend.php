<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="initial-scale=1, width=device-width" />
    <?= service('hooks')->trigger(hook('Frontend.head')) ?>
</head>

<body>
    <?= service('hooks')->trigger(hook('Frontend.header')) ?>
    <?= service('hooks')->trigger(hook('Frontend.main')) ?>
    <?= service('hooks')->trigger(hook('Frontend.footer')) ?>
</body>

</html>