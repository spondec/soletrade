<?php

namespace App\Trade\Telegram;

use App\Trade\Log;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Entities\Update;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Telegram;

class Bot
{
    protected Telegram $telegram;

    protected array $authenticatedChatIds = [];

    public function __construct(string $token, string $username, protected ?string $password = null)
    {
        $this->telegram = new Telegram($token, $username);
        $this->telegram->useGetUpdatesWithoutDatabase();
    }

    public function updates(): array
    {
        $response = $this->telegram->handleGetUpdates();

        return \array_filter(
            $response->getResult(),
            function (Update $update) {
                $message = $update->getMessage() ?? $update->getEditedMessage();

                return $this->authenticate($message->getText(), $message->getChat()->getId());
            }
        );
    }

    protected function authenticate(string $password, int $chatId): bool
    {
        if ($this->isAuthenticated($chatId)) {
            return true;
        }

        if (\trim($password) === '/password ' . \trim($this->password)) {
            $this->authenticatedChatIds[] = $chatId;
            return true;
        }

        Log::info('Authentication failed for Chat ID: ' . $chatId);

        return false;
    }

    protected function isAuthenticated(int $chatId): bool
    {
        return !$this->password || \in_array($chatId, $this->authenticatedChatIds);
    }

    public function sendMessage(string $message, int $chatId): ServerResponse
    {
        if (!$this->isAuthenticated($chatId)) {
            //should not happen because we are filtering out unauthenticated messages
            throw new \LogicException('Attempt to send message to unauthenticated chat.');
        }

        return Request::sendMessage([
            'chat_id' => $chatId,
            'text'    => $message,
        ]);
    }
}
