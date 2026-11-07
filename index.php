<?php

require './vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;

function readConfigFile()
{
    $file = __DIR__ . '/.config.json';
    if (!file_exists($file)) {
        return [];
    }
    $content = file_get_contents($file);
    return json_decode($content, true) ?? [];
}

function mailConfig(): PHPMailer
{
    $mail = new PHPMailer(true);
    //Server settings
    $mail->SMTPDebug = SMTP::DEBUG_SERVER;                      //Enable verbose debug output
    $mail->isSMTP();                                            //Send using SMTP
    $mail->Host       = readConfigFile()['host'];                     //Set the SMTP server to send through
    $mail->SMTPAuth   = true;                                   //Enable SMTP authentication
    $mail->Username   = readConfigFile()['username'];                     //SMTP username
    $mail->Password   = readConfigFile()['password'];                               //SMTP password
    $mail->Port       = readConfigFile()['port'];

    return $mail;
}

function sendMail(PHPMailer $mail, array $body)
{
    try {
        //Recipients
        $mail->setFrom('mail@tester.com', 'Mailer');
        $mail->addAddress($body['para']);     //Add a recipient
        $mail->addReplyTo('mail@tester.com', 'Information');
        $mail->addCC($body['cc']);

        //Content
        $mail->Subject = $body['assunto'];
        $mail->Body = $body['mensagem'];

        $mail->send();
        echo 'Message has been sent' . PHP_EOL;
    } catch (Exception $e) {
        echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
    }
}

$host = getenv('HOST');
$port = getenv('PORT');

$server = new Server($host, PORT, sock_type: SWOOLE_SOCK_TCP);

$server->set([
    'worker_num'      => 2,
    'task_worker_num' => 4,
]);

$server->on('start', function ($server) {
    echo "Swoole http server is started at http://{$server->host}:{$server->port}\n";
});

$server->on('request', function (Request $request, Response $response) use ($server) {
    if ($request->server['request_uri'] === '/' and $request->server['request_method'] === 'GET') {
        $response->header('Content-Type', 'text/html');

        $html_template = file_get_contents(__DIR__ . '/../templates/home.html');

        $response->end($html_template);
    }

    if ($request->server['request_uri'] === '/send' and $request->server['request_method'] === 'POST') {
        $response->header('Content-Type', 'text/html');

        if (!isset($request->post['para']) || !isset($request->post['assunto']) || !isset($request->post['mensagem'])) {
            $response->status(400);
            $response->end('Missing required parameter: para or assunto or mensagem');
            return;
        }

        $body = [
            "para" => htmlspecialchars_decode($request->post['para']),
            "cc" => htmlspecialchars_decode($request->post['cc'] ?? ''),
            "assunto" => htmlspecialchars_decode($request->post['assunto']),
            "mensagem" => htmlspecialchars_decode($request->post['mensagem']),
        ];


        $server->task($body, -1);

        echo 'Mail service foi enviado para o task-work' . PHP_EOL;

        $response->end('<h1>Email enviado!!</h1>');
    }

    $response->header('Content-Type', 'text/html');

    $html_template = file_get_contents(__DIR__ . '/../templates/notfound.html');

    $response->end($html_template);
});

$server->on('Task', function ($server, $taskId, $fromId, $data) {
    echo "Task #{$taskId} from worker #{$fromId} started.\n";
    $mail = mailConfig();
    sendMail($mail, $data);
    echo "Task #{$taskId} completed.\n";
});


$server->start();
