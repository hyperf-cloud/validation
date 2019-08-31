<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://doc.hyperf.io
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf-cloud/hyperf/blob/master/LICENSE
 */

namespace Hyperf\Validation\Support;

use Countable;
use Hyperf\Utils\Arr;
use Hyperf\Utils\Contracts\Arrayable;
use Hyperf\Utils\Contracts\Jsonable;
use Hyperf\Utils\Str;
use Hyperf\Validation\Contracts\Support\MessageBag as MessageBagContract;
use Hyperf\Validation\Contracts\Support\MessageProvider;
use JsonSerializable;

class MessageBag implements Arrayable, Countable, Jsonable, JsonSerializable, MessageBagContract, MessageProvider
{
    /**
     * All of the registered messages.
     *
     * @var array
     */
    protected $messages = [];

    /**
     * Default format for message output.
     *
     * @var string
     */
    protected $format = ':message';

    /**
     * Create a new message bag instance.
     */
    public function __construct(array $messages = [])
    {
        foreach ($messages as $key => $value) {
            $value = $value instanceof Arrayable ? $value->toArray() : (array) $value;

            $this->messages[$key] = array_unique($value);
        }
    }

    /**
     * Convert the message bag to its string representation.
     */
    public function __toString(): string
    {
        return $this->toJson();
    }

    /**
     * Get the keys present in the message bag.
     */
    public function keys(): array
    {
        return array_keys($this->messages);
    }

    /**
     * Add a message to the message bag.
     *
     * @return $this
     */
    public function add(string $key, string $message)
    {
        if ($this->isUnique($key, $message)) {
            $this->messages[$key][] = $message;
        }

        return $this;
    }

    /**
     * Merge a new array of messages into the message bag.
     *
     * @param array|MessageProvider $messages
     * @return $this
     */
    public function merge($messages)
    {
        if ($messages instanceof MessageProvider) {
            $messages = $messages->getMessageBag()->getMessages();
        }

        $this->messages = array_merge_recursive($this->messages, $messages);

        return $this;
    }

    /**
     * Determine if messages exist for all of the given keys.
     *
     * @param array|string $key
     */
    public function has($key): bool
    {
        if ($this->isEmpty()) {
            return false;
        }

        if (is_null($key)) {
            return $this->any();
        }

        $keys = is_array($key) ? $key : func_get_args();

        foreach ($keys as $key) {
            if ($this->first($key) === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine if messages exist for any of the given keys.
     *
     * @param array|string $keys
     */
    public function hasAny($keys = []): bool
    {
        if ($this->isEmpty()) {
            return false;
        }

        $keys = is_array($keys) ? $keys : func_get_args();

        foreach ($keys as $key) {
            if ($this->has($key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the first message from the message bag for a given key.
     *
     * @param string $key
     * @param string $format
     */
    public function first($key = null, $format = null): string
    {
        $messages = is_null($key) ? $this->all($format) : $this->get($key, $format);

        $firstMessage = Arr::first($messages, null, '');

        return is_array($firstMessage) ? Arr::first($firstMessage) : $firstMessage;
    }

    /**
     * Get all of the messages from the message bag for a given key.
     *
     * @param string $format
     */
    public function get(string $key, $format = null): array
    {
        // If the message exists in the message bag, we will transform it and return
        // the message. Otherwise, we will check if the key is implicit & collect
        // all the messages that match the given key and output it as an array.
        if (array_key_exists($key, $this->messages)) {
            return $this->transform(
                $this->messages[$key],
                $this->checkFormat($format),
                $key
            );
        }

        if (Str::contains($key, '*')) {
            return $this->getMessagesForWildcardKey($key, $format);
        }

        return [];
    }

    /**
     * Get all of the messages for every key in the message bag.
     *
     * @param string $format
     */
    public function all($format = null): array
    {
        $format = $this->checkFormat($format);

        $all = [];

        foreach ($this->messages as $key => $messages) {
            $all = array_merge($all, $this->transform($messages, $format, $key));
        }

        return $all;
    }

    /**
     * Get all of the unique messages for every key in the message bag.
     *
     * @param string $format
     */
    public function unique($format = null): array
    {
        return array_unique($this->all($format));
    }

    /**
     * Get the raw messages in the message bag.
     */
    public function messages(): array
    {
        return $this->messages;
    }

    /**
     * Get the raw messages in the message bag.
     */
    public function getMessages(): array
    {
        return $this->messages();
    }

    /**
     * Get the messages for the instance.
     *
     * @return MessageBag
     */
    public function getMessageBag()
    {
        return $this;
    }

    /**
     * Get the default message format.
     */
    public function getFormat(): string
    {
        return $this->format;
    }

    /**
     * Set the default message format.
     *
     * @return MessageBag
     */
    public function setFormat(string $format = ':message')
    {
        $this->format = $format;

        return $this;
    }

    /**
     * Determine if the message bag has any messages.
     */
    public function isEmpty(): bool
    {
        return ! $this->any();
    }

    /**
     * Determine if the message bag has any messages.
     */
    public function isNotEmpty(): bool
    {
        return $this->any();
    }

    /**
     * Determine if the message bag has any messages.
     */
    public function any(): bool
    {
        return $this->count() > 0;
    }

    /**
     * Get the number of messages in the message bag.
     */
    public function count(): int
    {
        return count($this->messages, COUNT_RECURSIVE) - count($this->messages);
    }

    /**
     * Get the instance as an array.
     */
    public function toArray(): array
    {
        return $this->getMessages();
    }

    /**
     * Convert the object into something JSON serializable.
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Convert the object to its JSON representation.
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * Determine if a key and message combination already exists.
     */
    protected function isUnique(string $key, string $message): bool
    {
        $messages = (array) $this->messages;

        return ! isset($messages[$key]) || ! in_array($message, $messages[$key]);
    }

    /**
     * Get the messages for a wildcard key.
     *
     * @param null|string $format
     */
    protected function getMessagesForWildcardKey(string $key, $format): array
    {
        return collect($this->messages)
            ->filter(function ($messages, $messageKey) use ($key) {
                return Str::is($key, $messageKey);
            })
            ->map(function ($messages, $messageKey) use ($format) {
                return $this->transform(
                    $messages,
                    $this->checkFormat($format),
                    $messageKey
                );
            })->all();
    }

    /**
     * Format an array of messages.
     */
    protected function transform(array $messages, string $format, string $messageKey): array
    {
        return collect($messages)
            ->map(function ($message) use ($format, $messageKey) {
                // We will simply spin through the given messages and transform each one
                // replacing the :message place holder with the real message allowing
                // the messages to be easily formatted to each developer's desires.
                return str_replace([':message', ':key'], [$message, $messageKey], $format);
            })->all();
    }

    /**
     * Get the appropriate format based on the given format.
     *
     * @param string $format
     * @return string
     */
    protected function checkFormat($format)
    {
        return $format ?: $this->format;
    }
}
