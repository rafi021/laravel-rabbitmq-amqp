<?php

namespace App\Services;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Connection\AMQPSSLConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use PhpAmqpLib\Message\AMQPMessage;

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
        string $exchange="test_exchange", 
        string $routing_key= "test_key",
        string $queue_name= "test_queue",
    ):void
    {
        [$connection, $channel ]= $this->createConnection();
        
        $channel->exchange_declare($exchange, AMQPExchangeType::DIRECT, false, false, false);
        $channel->queue_declare($queue_name, false, false, false, false);
        $channel->queue_bind($queue_name, $exchange, $routing_key);

        $msg = new AMQPMessage(json_encode($message));
        $channel->basic_publish($msg, $exchange, $routing_key);
        // echo " [x] Sent $message to $exchange / $queue_name.\n";
        $this->shutdown($channel, $connection);
    }
    public function consumeDireect(
        string $queue_name ="test_queue",
        string $routing_key= "",
    ):void
    {
        [$connection, $channel ]= $this->createConnection();

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
        string $routing_key="order.created",
        string $exchange="topic_exchange",
    ): void{
         [$connection, $channel ]= $this->createConnection();
         $channel->exchange_declare($exchange, AMQPExchangeType::TOPIC, false, true, false);

         $msg = new AMQPMessage(json_encode($message));
         $channel->basic_publish($msg, $exchange, $routing_key);
         $this->shutdown($channel, $connection);
    }

    public function consumeTopic(
        string $queue_name = "order_queue",
        string $topicPattern="order.*",
        string $exchange="topic_exchange", 
    ):void{
        [$connection, $channel ]= $this->createConnection();
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
        string $exchange= "fanout_exchange",
    ):void {
        [$connection, $channel ]= $this->createConnection();
        $channel->exchange_declare($exchange, AMQPExchangeType::FANOUT, false, true, false);
        $msg = new AMQPMessage(json_encode($message));
        $channel->basic_publish($msg, $exchange, '');
        $this->shutdown($channel, $connection);
    }

    public function consumeFanout(
        string $exchange="fanout_exchange",
        string $queue_name ="fanout_queue"
    ):void{
        [$connection, $channel ]= $this->createConnection();
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
}
