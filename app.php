<?php

require_once 'vendor/autoload.php';

try {
    $dotenv = new Dotenv\Dotenv(__DIR__);
    $dotenv->load();
} catch(Exception $e) {
    //
}

use MetzWeb\Instagram\Instagram;
use PhpAmqpLib\Connection\AMQPConnection;

$exchange     = 'router';
$queue        = 'msgs';
$port         = '5672';
$consumer_tag = 'consumer';

$amqpUrl = getenv('CLOUDAMQP_URL');
$parts   = explode('/', $amqpUrl);
$user    = $vhost = $parts[3];
$parts   = explode('@', $parts[2]);
$host    = $parts[1];
$pass    = str_replace($user.':', '', $parts[0]);

$pusher = new Pusher(getenv('PUSHER_KEY'), getenv('PUSHER_SECRET'), getenv('PUSHER_APP_ID'));

$conn = new AMQPConnection($host, $port, $user, $pass, $vhost);
$ch = $conn->channel();

$ch->queue_declare($queue, false, true, false, false);
$ch->exchange_declare($exchange, 'direct', false, true, false);
$ch->queue_bind($queue, $exchange);

$ig = new Instagram(getenv('INSTAGRAM_API_KEY'));

$ch->basic_consume($queue, $consumer_tag, false, false, false, false, function($msg) {
    global $pusher, $ig;
    error_log('Triggering pusher event!');
    $message = json_decode($msg->body, true);

    if ($message['source'] == 'ig') {
       $igData = $ig->getTagMedia('skunenieki', 1);
       error_log(json_encode($igData));

       $message = array(
           'source' => 'ig',
           'time'   => $igData->data[0]->created_time,
           'url'    => $igData->data[0]->images->standard_resolution->url,
           'author' => $igData->data[0]->user->username,
       );
    }

    $pusher->trigger('sk-image-display', 'display', $message);
    sleep(60);

    $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
});

/**
 * @param \PhpAmqpLib\Channel\AMQPChannel $ch
 * @param \PhpAmqpLib\Connection\AbstractConnection $conn
 */
function shutdown($ch, $conn)
{
    $ch->close();
    $conn->close();
}

register_shutdown_function('shutdown', $ch, $conn);

// Loop as long as the channel has callbacks registered
while (count($ch->callbacks)) {
    $ch->wait();
}
