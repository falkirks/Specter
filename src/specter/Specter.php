<?php
namespace specter;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\math\Vector3;
use pocketmine\network\protocol\MessagePacket;
use pocketmine\plugin\PluginBase;
use specter\api\Player;
use specter\network\SpecterInterface;
use specter\network\SpecterPlayer;

class Specter extends PluginBase{
    /** @var  SpecterInterface */
    private $interface;
    public function onEnable(){
        $this->interface =  new SpecterInterface($this);
        $this->getServer()->addInterface($this->interface);
    }
    public function onCommand(CommandSender $sender, Command $command, $label, array $args){
        if(isset($args[0])){
            switch($args[0]){
                case 'spawn':
                    if(isset($args[1])) {
                        if ($this->getInterface()->openSession($args[1])){
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
                case 'move':
                    if(isset($args[4])) {
                        $player = $this->getServer()->getPlayer($args[1]);
                        if($player instanceof SpecterPlayer){
                            $player->teleport(new Vector3($args[2], $args[3], $args[4])); //TODO make lower level
                        }
                    }
                    else{
                        return false;
                    }
                    break;
                case 'attack':

                    break;
                case 'chat':
                    if(isset($args[2])) {
                        $player = $this->getServer()->getPlayer($args[1]);
                        if($player instanceof SpecterPlayer){
                            $pk = new MessagePacket();
                            $pk->source = "";
                            $pk->message = implode(" ", array_slice($args, 2));
                            $this->getInterface()->queueReply($pk, $player->getName());
                        }
                        return true;
                    }
                    else{
                        return false;
                    }
                    break;
            }
        }
        else{
            return false;
        }
    }
    /**
     * @return SpecterInterface
     */
    public function getInterface(){
        return $this->interface;
    }

}