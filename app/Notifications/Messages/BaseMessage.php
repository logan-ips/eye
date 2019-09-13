<?php

namespace Eyewitness\Eye\Notifications\Messages;

use Eyewitness\Eye\Eye;
use Eyewitness\Eye\Repo\Notifications\Severity;
use Eyewitness\Eye\Notifications\Messages\MessageInterface;
use Illuminate\Support\Str;

abstract class BaseMessage implements MessageInterface
{
    /**
     * The meta data.
     *
     * @var array
     */
    protected $meta;

    /**
     * The message constructor can accept an array of meta data.
     *
     * @param  array  $data
     * @return void
     */
    public function __construct($data = [])
    {
        $this->meta = $data;
    }

    /**
     * Any meta information for the message.
     *
     * @return array
     */
    public function meta()
    {
        return $this->meta;
    }

    /**
     * A markup version of the message.
     *
     * @return string
     */
    public function markupDescription()
    {
        return $this->plainDescription();
    }

    /**
     * The severity level for this message.
     *
     * @return string
     */
    protected function getSeverity($default)
    {
        if ($severity = Severity::where('namespace', $this->safeType())->where('notification', get_class($this))->first()) {
            return $severity->severity;
        }

        app(Eye::class)->logger()->debug('Default Notification not found in database', get_class($this));

        return $default;
    }

    /**
     * Get a safe version of the message type
     *
     * @return string
     */
    public function safeType()
    {
        return Str::snake($this->type());
    }
}
