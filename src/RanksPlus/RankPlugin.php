<?php

namespace RanksPlus;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

class RankPlugin extends PluginBase implements Listener {

    private $ranks;
    private $permissions;

    public function onEnable(): void {
        $this->saveResource("ranks.yml", false);
        $this->saveResource("permissions.yml", false);

        $this->ranks = new Config($this->getDataFolder() . "ranks.yml", Config::YAML);
        $this->permissions = new Config($this->getDataFolder() . "permissions.yml", Config::YAML);

        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getLogger()->info("RankPlus has been enabled!");

        // Register the "ranksplus.setrank" permission node if not already registered
        $permission = $this->getServer()->getPluginManager()->getPermission("ranksplus.setrank");
        if ($permission === null) {
            $this->getServer()->getPluginManager()->addPermission(new \pocketmine\permission\Permission("ranksplus.setrank", "Allows players to set ranks."));
        }
    }

    public function onJoin(PlayerJoinEvent $event): void {
        $player = $event->getPlayer();
        $name = $player->getName();

        // Check if PiggyFactions is loaded
        $piggyFactions = $this->getServer()->getPluginManager()->getPlugin("PiggyFactions");
        if ($piggyFactions !== null && $piggyFactions->isEnabled() && method_exists($piggyFactions, 'getPlayerFaction')) {
            $faction = $piggyFactions->getPlayerFaction($player);
            // Handle faction-related logic here (e.g., add faction tag to player's name)
            $player->setNameTag("[$faction] " . $player->getName());
        }

        // Check if the player already has a rank
        if (!$this->ranks->exists(strtolower($name))) {
            // Player doesn't have a rank, set default rank
            $this->setPlayerRank($name, 'Member');
        }

        // Get player's rank
        $rank = $this->getPlayerRank($name);

        // Apply permissions
        $player->addAttachment($this, $rank['permissions'], true);

        // Apply prefix and suffix
        $player->setNameTag($rank['prefix'] . $name . $rank['suffix']);

        // Set display name with rank
        $player->setDisplayName($rank['prefix'] . $name . $rank['suffix']);

        // Set the rank above the player's head
        $player->sendPopup($rank['prefix'] . $rank['suffix']);
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if ($command->getName() === "setrank" && $sender->hasPermission("ranksplus.setrank")) {
            if (count($args) === 2) {
                $rankName = $args[0];
                $playerName = $args[1];

                $this->setPlayerRank($playerName, $rankName);
                $sender->sendMessage("Set rank of $playerName to $rankName.");
                return true;
            } else {
                $sender->sendMessage("Usage: /setrank <rank> <player>");
                return false;
            }
        }
        return false;
    }

    private function setPlayerRank($playerName, $rankName): void {
        // Set the rank for the player in the ranks.yml file
        $this->ranks->set(strtolower($playerName), [
            'prefix' => TextFormat::WHITE, // You can customize the default prefix
            'suffix' => TextFormat::RESET, // You can customize the default suffix
            'permissions' => $this->getRankPermissions($rankName),
        ]);
        $this->ranks->save();
    }

    private function getRankPermissions($rankName): array {
        // Get the permissions associated with the specified rank
        $rankPermissions = $this->ranks->get($rankName, []);

        return $rankPermissions['permissions'] ?? [];
    }

    private function getPlayerRank($playerName): array {
        $defaultRank = [
            'prefix' => TextFormat::WHITE,
            'suffix' => TextFormat::RESET,
            'permissions' => [],
        ];

        $playerRank = $this->ranks->get(strtolower($playerName), $defaultRank);

        // Merge player-specific permissions with rank permissions
        $playerPermissions = $this->permissions->get(strtolower($playerName), []);
        $mergedPermissions = array_merge($playerRank['permissions'], $playerPermissions);

        return [
            'prefix' => $playerRank['prefix'],
            'suffix' => $playerRank['suffix'],
            'permissions' => $mergedPermissions,
        ];
    }
}
