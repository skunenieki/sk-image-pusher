<?php

require_once 'vendor/autoload.php';

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

$conn = new AMQPConnection($host, $port, $user, $pass, $vhost);
$ch = $conn->channel();

$ch->queue_declare($queue, false, true, false, false);
$ch->exchange_declare($exchange, 'direct', false, true, false);
$ch->queue_bind($queue, $exchange);

/**
 * @param \PhpAmqpLib\Message\AMQPMessage $msg
 */
function process_message($msg)
{
    error_log($msg->body);

    $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
}

$ch->basic_consume($queue, $consumer_tag, false, false, false, false, 'process_message');

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