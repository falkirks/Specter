<?php
namespace specter;

use icontrolu\iControlU;
use pocketmine\block\Air;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\entity\Entity;
use pocketmine\event\Listener;
use pocketmine\event\player\cheat\PlayerIllegalMoveEvent;
use pocketmine\network\mcpe\protocol\InteractPacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\network\mcpe\protocol\RespawnPacket;
use pocketmine\network\mcpe\protocol\TextPacket;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use specter\network\SpecterInterface;
use specter\network\SpecterPlayer;

class Specter extends PluginBase implements Listener {
    /** @var  SpecterInterface */
    private $interface;
    public function onEnable(){
        $this->saveDefaultConfig();
        $this->interface =  new SpecterInterface($this);
        $this->getServer()->getNetwork()->registerInterface($this->interface);
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }
    public function onCommand(CommandSender $sender, Command $command, $label, array $args){
        if(isset($args[0])){
            switch($args[0]){
                case 'spawn':
                case 'new':
                case 'add':
                case 's':
                    if(isset($args[1])) {
                        if ($this->getInterface()->openSession($args[1], isset($args[2]) ? $args[2] : "SPECTER", isset($args[3]) ? $args[3] : 19133)){
                            $sender->sendMessage("Session started.");
                        }
                        else{
                            $sender->sendMessage("Failed to open session");
                        }
                        return true;
                    }
                    else{
                        return false;
                    }
                    break;
                case 'kick':
                case 'quit':
                case 'close':
                case 'q':
                    if(isset($args[1])) {
                        $player = $this->getServer()->getPlayer($args[1]);
                        if($player instanceof SpecterPlayer){
                            $player->close("", "client disconnect.");
                        }
                        else{
                            $sender->sendMessage("That player isn't managed by specter.");
                        }
                    }
                    else{
                        $sender->sendMessage("Usage: /specter quit <p>");
                    }
                    return true;
                    break;
                case 'move':
                case 'm':
                case 'teleport':
                case 'tp':
                    if(isset($args[4])) {
                        $player = $this->getServer()->getPlayer($args[1]);
                        if($player instanceof SpecterPlayer){
                            $pk = new MovePlayerPacket();
                            $pk->x = $args[2];
                            $pk->y = $args[3] + $player->getEyeHeight();
                            $pk->z = $args[4];
                            $pk->yaw = $player->getYaw()+10; //This forces movement even if the movement is not large enough
                            $pk->bodyYaw = 0;
                            $pk->pitch = 0;
                            $this->interface->queueReply($pk, $player->getName());
                        }
                        else{
                            $sender->sendMessage("That player isn't managed by specter.");
                        }
                    }
                    else{
                        $sender->sendMessage("Usage: /specter move  <p> <x> <y> <z>");
                    }
                    return true;
                    break;
                case 'attack':
                case 'a':
                    if(isset($args[2])){
                        $player = $this->getServer()->getPlayer($args[1]);
                        if($player instanceof SpecterPlayer){
                            if(substr($args[2], 0, 4) === "eid:"){
                                $victimId = substr($args[2], 4);
                                if(!is_numeric($victimId)){
                                    $sender->sendMessage("Usage: /specter attack <attacker> <victim>|<eid:<victim eid>>");
                                    return true;
                                }
                                if(!($player->getLevel()->getEntity($victimId) instanceof Entity)){
                                    $sender->sendMessage("There is no entity with entity ID $victimId in {$player->getName()}'s level");
                                    return true;
                                }
                            }else{
                                $victim = $this->getServer()->getPlayer($args[2]);
                                if($victim instanceof Player){
                                    $victimId = $victim->getId();
                                }
                                else{
                                    $sender->sendMessage("Player $args[2] not found");
                                    return true;
                                }
                            }
                            $pk = new InteractPacket();
                            $pk->action = InteractPacket::ACTION_RIGHT_CLICK; // unused
                            $pk->target = $victimId;
                            $this->getInterface()->queueReply($pk, $player->getName());
                        }
                        else{
                            $sender->sendMessage("That player isn't managed by specter.");
                        }
                    }
                    else{
                        $sender->sendMessage("Usage: /specter attack <attacker> [eid:]<victim>");
                    }
                    return true;
                    break;
                case 'c':
                case 'chat':
                case 'command':
                    if(isset($args[2])) {
                        $player = $this->getServer()->getPlayer($args[1]);
                        if($player instanceof SpecterPlayer){
                            $pk = new TextPacket();
	                        $pk->type = TextPacket::TYPE_CHAT;
                            $pk->source = "";
                            $pk->message = implode(" ", array_slice($args, 2));
                            $this->getInterface()->queueReply($pk, $player->getName());
                        }
                        else{
                            $sender->sendMessage("That player isn't managed by specter.");
                        }
                    }
                    else{
                        $sender->sendMessage("Usage: /specter chat <p> <data>");
                    }
                    return true;
                    break;
                case 'control': //TODO update iControlU with better support
                case 'icu':
                    if($sender instanceof Player) {
                        $icu = $this->getICU();
                        if($icu instanceof iControlU) {
                            $player = $this->getServer()->getPlayer($args[1]);
                            if ($player instanceof SpecterPlayer) {
                                if($icu->isControl($sender)) {
                                    $this->getServer()->dispatchCommand($sender, "icu control " . $args[1]);
                                }
                                else{
                                    $this->getServer()->dispatchCommand($sender, "icu stop ");
                                }
                            }
                            else{
                                $sender->sendMessage("That player isn't a specter player");
                            }
                        }
                        else{
                            $sender->sendMessage("You need to have iControlU to use this feature.");
                        }
                    }
                    else{
                        $sender->sendMessage("This command must be run in game.");
                    }
                    return true;
                    break;
                case "respawn":
                case "r":
                    if(!isset($args[1])){
                        $sender->sendMessage("Usage: /specter respawn <player>");
                        return true;
                    }
                    $player = $this->getServer()->getPlayer($args[1]);
                    if($player instanceof SpecterPlayer){
                        if(!$player->spec_needRespawn){
                            $this->interface->queueReply(new RespawnPacket(), $player->getName());
                        }
                        else{
                            $sender->sendMessage("{$player->getName()} doesn't need respawning.");
                        }
                    }
                    else{
                        $sender->sendMessage("That player isn't a specter player");
                    }
                    return true;
                    break;
            }
        }
        return false;
    }

    /**
     * @priority HIGHEST
     * @param PlayerIllegalMoveEvent $event
     */
    public function onIllegalMove(PlayerIllegalMoveEvent $event){
        if($event->getPlayer() instanceof SpecterPlayer && $this->getConfig()->get('allowIllegalMoves')){
            $event->setCancelled();
        }
    }
/*
    /**
     * @priority MONITOR
     * @param DataPacketReceiveEvent $pk

    public function onDataPacketRecieve(DataPacketReceiveEvent $pk){
        if($pk->getPacket() instanceof RequestChunkRadiusPacket){
            $this->getLogger()->info("RADIUS:" . $pk->getPacket()->radius);
        }
        $this->getLogger()->info("GOT:" . get_class($pk->getPacket()));
    }

    /**
     * @priority MONITOR
     * @param DataPacketSendEvent $pk

    public function onDataPacketSend(DataPacketSendEvent $pk){
        if(!($pk->getPacket() instanceof SetTimePacket)) {
            $this->getLogger()->info("SEND:" . get_class($pk->getPacket()));
        }
    }
*/
    /**
     * @return SpecterInterface
     */
    public function getInterface(){
        return $this->interface;
    }

    /**
     * @return null|\icontrolu\iControlU
     */
    public function getICU(){
        return $this->getServer()->getPluginManager()->getPlugin("iControlU");
    }
}
