<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// GPIO pin number
$pin = 21;

// Handle brightness adjustment
if (isset($_POST['brightness'])) {
    error_log('Brightness: ' . $_POST['brightness']);
    $targetBrightness = filter_var($_POST['brightness'], FILTER_VALIDATE_INT, [
        'options' => ['default' => 0, 'min_range' => 0, 'max_range' => 255]
    ]);

    exec('/usr/bin/pigs p ' . escapeshellarg($pin) . ' ' . escapeshellarg($targetBrightness));
    exit;
}

// Handle auto-brightness toggle
if (isset($_POST['autoBrightness'])) {
    error_log('AutoBrightness: ' . $_POST['autoBrightness']);
    $autoBrightness = filter_var($_POST['autoBrightness'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

    $configFile = '/var/www/html/window.conf';
    $json = json_decode(file_get_contents($configFile), true);

    if ($json === null) {
        http_response_code(500);
        exit('Error reading configuration file.');
    }

    $json['auto'] = $autoBrightness ? 1 : 0;

    if (file_put_contents($configFile, json_encode($json)) === false) {
        http_response_code(500);
        exit('Error writing to configuration file.');
    }

    if ($autoBrightness) {
        exec('/home/pi/window.py');
    }

    echo exec('/usr/bin/pigs gdc ' . escapeshellarg($pin));
    exit;
}

// Get current brightness value
$brightness = exec('/usr/bin/pigs gdc ' . escapeshellarg($pin));

// Get auto-brightness setting
$configFile = '/var/www/html/window.conf';
$isAutoBrightness = json_decode(file_get_contents($configFile))->auto ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Smart Window</title>

    <!-- Bootstrap -->
    <link href="/css/bootstrap.min.css" rel="stylesheet">
    <link href="/css/custom.css" rel="stylesheet">

    <!-- HTML5 shim and Respond.js for IE8 support -->
    <!--[if lt IE 9]>
      <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
      <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
</head>
<body>
<nav class="navbar navbar-inverse navbar-fixed-top">
    <div class="container">
        <div class="navbar-header">
            <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar" aria-expanded="false" aria-controls="navbar">
                <span class="sr-only">Toggle navigation</span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </button>
            <a class="navbar-brand" href="#">Smart Window</a>
        </div>
        <div id="navbar" class="collapse navbar-collapse">
            <ul class="nav navbar-nav">
                <li class="active"><a href="#">Home</a></li>
            </ul>
        </div>
    </div>
</nav>

<div class="container">
    <div class="starter-template">
        <h3>Brightness</h3>
        <output for="fader" id="volume"><?php echo round($brightness / 2.55) ?>%</output><br/><br/>

        <div class="divider"></div>

        <div class="dim">
            <img src="/img/brightness-dim.png" alt="Dim">
        </div>
        <div class="bright">
            <img src="/img/brightness-bright.png" alt="Bright">
        </div>
        <div class="slider">
            <input type="range" min="0" max="255" step="8" value="<?php echo $brightness ?>" id="fader">
        </div>
        <div class="clearfix"></div><br/>
        <div class="divider"></div>

        <a href="#" class="btn btn-<?php echo ($isAutoBrightness) ? 'primary' : 'default' ?> pull-right" style="width: 33%" id="autobrightness">
            <span class="glyphicon glyphicon-ok"></span>
        </a>

        <div class="pull-left" style="margin-top: 5px">Auto-Brightness</div>
        <br/><br/><br/>
        <div class="divider"></div>
    </div>
</div>

<script>
    // Debounce function to limit AJAX calls
    function debounce(func, delay) {
        let timer;
        return function (...args) {
            clearTimeout(timer);
            timer = setTimeout(() => func.apply(this, args), delay);
        };
    }

    // Update brightness output and send AJAX request
    const outputUpdate = debounce((vol) => {
        $('#volume').text(Math.round(vol / 2.55) + '%');

        if ($('#autobrightness').hasClass('btn-primary')) {
            $('#autobrightness').trigger('click');
        }

        $.ajax({
            method: "POST",
            url: "index.php",
            data: { brightness: vol },
            error: () => alert('Failed to update brightness.')
        });
    }, 200);

    // Toggle auto-brightness
    function updateAutoBrightness() {
        const elem = $('#autobrightness');
        elem.toggleClass('btn-primary btn-default');

        const isAuto = elem.hasClass('btn-primary') ? 1 : 0;

        $.ajax({
            method: "POST",
            url: "index.php",
            data: { autoBrightness: isAuto },
            success: (msg) => {
                $('input[type=range]').val(msg);
                $('#volume').text(Math.round(msg / 2.55) + '%');
            },
            error: () => alert('Failed to update auto-brightness.')
        });
    }

    // Event listeners
    $('#fader').on('input', function () {
        outputUpdate(this.value);
    });

    $('#autobrightness').on('click', updateAutoBrightness);
</script>
</body>
</html>
