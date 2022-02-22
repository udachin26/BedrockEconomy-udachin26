<?php

/**
 *  Copyright (c) 2022 cooldogedev
 *
 *  Permission is hereby granted, free of charge, to any person obtaining a copy
 *  of this software and associated documentation files (the "Software"), to deal
 *  in the Software without restriction, including without limitation the rights
 *  to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 *  copies of the Software, and to permit persons to whom the Software is
 *  furnished to do so, subject to the following conditions:
 *
 *  The above copyright notice and this permission notice shall be included in all
 *  copies or substantial portions of the Software.
 *
 *  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 *  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 *  FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 *  AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 *  LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 *  OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 *  SOFTWARE.
 */

declare(strict_types=1);

namespace cooldogedev\BedrockEconomy\addon\scorehud;

use cooldogedev\BedrockEconomy\event\transaction\TransactionProcessEvent;
use cooldogedev\BedrockEconomy\transaction\types\TransferTransaction;
use cooldogedev\BedrockEconomy\transaction\types\UpdateTransaction;
use Ifera\ScoreHud\event\PlayerTagUpdateEvent;
use Ifera\ScoreHud\event\TagsResolveEvent;
use Ifera\ScoreHud\scoreboard\ScoreTag;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\event\player\PlayerQuitEvent;

final class ScoreHudListener implements Listener
{
    public function __construct(protected ScoreHudAddon $parent)
    {
    }

    /**
     * Caches the initial balance from the db.
     *
     * @param PlayerLoginEvent $event
     */
    public function onPlayerLogin(PlayerLoginEvent $event): void
    {
        $player = $event->getPlayer();
        $this->getParent()->initializePlayerCache($player->getName());
    }

    public function getParent(): ScoreHudAddon
    {
        return $this->parent;
    }

    /**
     * Sends the tag to the player.
     *
     * @param PlayerLoginEvent $event
     */
    public function onPlayerJoin(PlayerLoginEvent $event): void
    {
        $player = $event->getPlayer();
        $balance = $this->getParent()->getPlayerCache($player->getName());

        $event = new PlayerTagUpdateEvent($player, new ScoreTag(ScoreHudAddon::SCOREHUD_TAG_BALANCE, $balance !== null ? (string)$balance : "N/A"));
        $event->call();
    }

    /**
     * Removes the player from the cache.
     *
     * @param PlayerQuitEvent $event
     */
    public function onPlayerQuit(PlayerQuitEvent $event): void
    {
        $player = $event->getPlayer();
        $this->getParent()->removePlayerCache($player->getName());
    }

    /**
     * Updates the tag when the balance changes if the player is connected to the server.
     *
     * @param TransactionProcessEvent $event
     */
    public function onTransactionProcess(TransactionProcessEvent $event): void
    {
        $transaction = $event->getTransaction();

        if ($transaction instanceof UpdateTransaction) {
            $this->getParent()->updatePlayerCache($transaction->getTarget());
        } elseif ($transaction instanceof TransferTransaction) {
            $this->getParent()->updatePlayerCache($transaction->getSender());
            $this->getParent()->updatePlayerCache($transaction->getReceiver());
        }
    }

    /**
     * Intercepts the tag resolve event and adds the balance tag.
     *
     * @param TagsResolveEvent $event
     */
    public function onTagResolve(TagsResolveEvent $event): void
    {
        $player = $event->getPlayer();
        $tag = $event->getTag();

        switch ($tag->getName()) {
            case ScoreHudAddon::SCOREHUD_TAG_BALANCE:
                $tag->setValue((string)($this->getParent()->getPlayerCache($player->getName()) ?? "N/A"));
        }
    }
}
