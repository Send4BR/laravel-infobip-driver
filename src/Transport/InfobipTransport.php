<?php

namespace Send4\InfobipMail\Transport;

use GuzzleHttp\ClientInterface;
use Illuminate\Mail\Transport\Transport;
use Swift_Mime_SimpleMessage;
use Swift_Attachment;
use Swift_Image;

class InfobipTransport extends Transport
{
    /**
     * Guzzle client instance.
     *
     * @var \GuzzleHttp\ClientInterface
     */
    protected $client;

    /**
     * The Infobip API key.
     * 
     * https://dev.infobip.com/getting-started/security-and-authorization
     * 
     * @var string
     */
    protected $key;

    /**
     * The Infobip base url.
     * 
     * https://dev.infobip.com/getting-started/base-url
     *
     * @var string
     */
    protected $baseUrl;

    /**
     * Create a new Infobip transport instance.
     *
     * @param  \GuzzleHttp\ClientInterface  $client
     * @param  string  $key
     * @param  string  $baseUrl
     * @return void
     */
    public function __construct(ClientInterface $client, $key, $baseUrl)
    {
        $this->client = $client;
        $this->key = $key;
        $this->baseUrl = $baseUrl;
    }

    /**
     * {@inheritdoc}
     */
    public function send(Swift_Mime_SimpleMessage $message, &$failedRecipients = null)
    {
        $this->beforeSendPerformed($message);

        $response = $this->client->request(
            'POST',
            "{$this->baseUrl}/email/1/send",
            $this->payload($message)
        );

        $this->sendPerformed($message);

        return $this->numberOfRecipients($message);
    }

    /**
     * Get the HTTP payload for sending the Infobip message.
     *
     * @param  \Swift_Mime_SimpleMessage  $message
     * @return array
     */
    protected function payload(Swift_Mime_SimpleMessage $message)
    {
        $payload = [
            'headers' => [
                'Authorization' => 'App ' . $this->key,
                'Accept' => 'application/json'
            ],
            'multipart' => [
                ['name' => 'from', 'contents' => $this->getFrom($message)],
                ['name' => 'subject', 'contents' => $message->getSubject()],
                ['name' => 'html', 'contents' => $message->getBody()],
            ],
        ];

        // Add "to" attribute to multipart payload
        foreach ($this->getTo($message) as $to) {
            $payload['multipart'][] = ['name' => 'to', 'contents' => $to];
        }

        // Add "replyTo" attribute to multipart payload
        if ($replyTo = $this->getReplyTo($message)) {
            $payload['multipart'][] = ['name' => 'replyTo', 'contents' => $replyTo];
        }

        // Add "attachments" attribute to multipart payload
        foreach ($this->getAttachments($message) as $attachment) {
            $payload['multipart'][] = [
                'name' => 'attachment', 
                'contents' => $attachment['contents'],
                'filename' => $attachment['filename'],
            ];
        }

        return $payload;
    }

    /**
     * Get all the addresses this message should be sent to.
     *
     * @param  \Swift_Mime_SimpleMessage  $message
     * @return array
     */
    protected function getTo(Swift_Mime_SimpleMessage $message)
    {
        $to = array_merge(
            (array) $message->getTo(), 
            (array) $message->getCc(), 
            (array) $message->getBcc()
        );

        return array_keys($to);
    }

    /**
     * Get the "from" payload field for the API request.
     *
     * @param  \Swift_Mime_SimpleMessage  $message
     * @return string
     */
    protected function getFrom(Swift_Mime_SimpleMessage $message)
    {
        $from = $message->getSender() ?: $message->getFrom();

        $address = array_keys($from)[0];
        $display = array_values($from)[0];

        return $display ? $display." <{$address}>" : $address;
    }

    /**
     * Get the "replyTo" payload field for the API request.
     *
     * @param  \Swift_Mime_SimpleMessage  $message
     * @return string
     */
    protected function getReplyTo(Swift_Mime_SimpleMessage $message)
    {
        $replyTo = $message->getReplyTo();

        return $replyTo ? array_keys($replyTo)[0] : null;
    }

    /**
     * @param Swift_Mime_SimpleMessage $message
     * @return array
     */
    private function getAttachments(Swift_Mime_SimpleMessage $message)
    {
        $attachments = [];

        foreach ($message->getChildren() as $attachment) {
            if (!$attachment instanceof Swift_Attachment && !$attachment instanceof Swift_Image) {
                continue;
            }

            $attachments[] = [
                'contents' => $attachment->getBody(),
                'filename' => $attachment->getFilename(),
                'type' => $attachment->getContentType(),
            ];
        }

        return $attachments;
    }

}
