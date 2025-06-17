<?php

namespace App\Services;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Connection\AMQPSSLConnection;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

class RabbitMQService
{
    private $connection;

    private function createConnection()
    {
        $connection = new AMQPStreamConnection(
            config("rabbitmq.RABBITMQ_HOST"),
            config("rabbitmq.RABBITMQ_PORT"),
            config("rabbitmq.RABBITMQ_USER"),
            config("rabbitmq.RABBITMQ_PASS"),
        );
        $channel = $connection->channel();
        return [$connection, $channel];
    }

    private function shutdown($channel, $connection)
    {
        $channel->close();
        $connection->close();
    }


    public function publishDirect(
        $message,
        string $exchange = "test_exchange",
        string $routing_key = "test_key",
        string $queue_name = "test_queue",
    ): void {
        [$connection, $channel] = $this->createConnection();

        $channel->exchange_declare($exchange, AMQPExchangeType::DIRECT, false, false, false);
        $channel->queue_declare($queue_name, false, false, false, false);
        $channel->queue_bind($queue_name, $exchange, $routing_key);

        $msg = new AMQPMessage(json_encode($message));
        $channel->basic_publish($msg, $exchange, $routing_key);
        // echo " [x] Sent $message to $exchange / $queue_name.\n";
        $this->shutdown($channel, $connection);
    }
    public function consumeDireect(
        string $queue_name = "test_queue",
        string $routing_key = "",
    ): void {
        [$connection, $channel] = $this->createConnection();

        $callback = function ($msg) {
            echo ' [x] Received ', json_decode($msg->body, true), "\n";
        };

        $channel->queue_declare($queue_name, false, false, false, false);

        $channel->basic_consume($queue_name, '', false, true, false, false, $callback);

        echo 'Waiting for new message on test_queue', " \n";
        while ($channel->is_consuming()) {
            $channel->wait();
        }
        $this->shutdown($channel, $connection);
    }

    public function publishTopic(
        $message,
        string $routing_key = "order.created",
        string $exchange = "topic_exchange",
    ): void {
        [$connection, $channel] = $this->createConnection();
        $channel->exchange_declare($exchange, AMQPExchangeType::TOPIC, false, true, false);

        $msg = new AMQPMessage(json_encode($message));
        $channel->basic_publish($msg, $exchange, $routing_key);
        $this->shutdown($channel, $connection);
    }

    public function consumeTopic(
        string $queue_name = "order_queue",
        string $topicPattern = "order.*",
        string $exchange = "topic_exchange",
    ): void {
        [$connection, $channel] = $this->createConnection();
        $channel->exchange_declare($exchange, AMQPExchangeType::TOPIC, false, true, false);
        $channel->queue_declare($queue_name, false, true, false, false);
        $channel->queue_bind($queue_name, $exchange, $topicPattern);

        $callback = function ($msg) {
            echo ' [x] Received ', json_decode($msg->body, true), "\n";
        };

        $channel->basic_consume($queue_name, '', false, false, false, false, $callback);

        echo 'Waiting for new message on test_queue', " \n";
        while ($channel->is_consuming()) {
            $channel->wait();
        }
        $this->shutdown($channel, $connection);
    }

    public function publishFanout(
        $message,
        string $exchange = "fanout_exchange",
    ): void {
        [$connection, $channel] = $this->createConnection();
        $channel->exchange_declare($exchange, AMQPExchangeType::FANOUT, false, true, false);
        $msg = new AMQPMessage(json_encode($message));
        $channel->basic_publish($msg, $exchange, '');
        $this->shutdown($channel, $connection);
    }

    public function consumeFanout(
        string $exchange = "fanout_exchange",
        string $queue_name = "fanout_queue"
    ): void {
        [$connection, $channel] = $this->createConnection();
        $channel->exchange_declare($exchange, AMQPExchangeType::FANOUT, false, true, false);
        $channel->queue_declare($queue_name, false, true, true, false);
        $channel->queue_bind($queue_name, $exchange, '');

        $callback = function ($msg) {
            echo ' [x] Received ', json_decode($msg->body, true), "\n";
        };

        $channel->basic_consume($queue_name, '', false, false, false, false, $callback);

        echo 'Waiting for new message on test_queue', " \n";
        while ($channel->is_consuming()) {
            $channel->wait();
        }
        $this->shutdown($channel, $connection);
    }

    public function publishHeaders(array $headers, $message, string $exchange = "header_exchange"): void
    {
        [$connection, $channel] = $this->createConnection();
        $channel->exchange_declare($exchange, AMQPExchangeType::HEADERS, false, true, false);
        $msg = new AMQPMessage(json_encode($message));
        $appHeaders = new AMQPTable(array_keys($headers));
        $msg->set('application_headers', $appHeaders);
        $channel->basic_publish($msg, $exchange, '');
        $this->shutdown($channel, $connection);
    }

    public function consumeHeaders(string $exchange = "header_exchange"): void
    {
        [$connection, $channel] = $this->createConnection();
        $channel->exchange_declare($exchange, AMQPExchangeType::HEADERS);
        list($queue_name, ,) = $channel->queue_declare('', false, false, true);

        $bindArguments = [
            "x-match" => "any",
            "notification-type-comment" => "comment",
            "notification-type-like" => "like",
        ];

        $channel->queue_bind($queue_name, $exchange, '', false, new AMQPTable($bindArguments));

        $callback = function (AMQPMessage $message) {
            echo PHP_EOL . ' [x] ', $message->getRoutingKey(), ':', $message->getBody(), "\n";
            echo 'Message headers follows' . PHP_EOL;
            var_dump($message->get('application_headers')->getNativeData());
            echo PHP_EOL;
        };

        $channel->basic_consume($queue_name, '', false, true, true, false, $callback);

        echo 'Waiting for new message on test_queue', " \n";
        while ($channel->is_consuming()) {
            try {
                $channel->wait(null, false, 2);
            } catch (AMQPTimeoutException $exception) {
            }
            echo '*' . PHP_EOL;
        }
        $this->shutdown($channel, $connection);
    }
}
