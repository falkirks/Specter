<?php
namespace specter\network;

use pocketmine\network\protocol\DataPacket;
use pocketmine\network\protocol\FullChunkDataPacket;
use pocketmine\network\protocol\Info;
use pocketmine\network\protocol\LoginPacket;
use pocketmine\network\protocol\RespawnPacket;
use pocketmine\network\protocol\SetHealthPacket;
use pocketmine\network\protocol\SetSpawnPositionPacket;
use pocketmine\network\protocol\SetTimePacket;
use pocketmine\network\protocol\StartGamePacket;
use pocketmine\network\protocol\TextPacket;
use pocketmine\network\SourceInterface;
use pocketmine\Player;
use pocketmine\utils\TextFormat;
use pocketmine\utils\UUID;
use specter\Specter;

class SpecterInterface implements SourceInterface{
    /** @var  Player[]|\SplObjectStorage */
    private $sessions;
    /** @var  Specter */
    private $specter;
    /** @var  array */
    private $ackStore;
    /** @var  array */
    private $replyStore;
    public function __construct(Specter $specter){
        $this->specter = $specter;
        $this->sessions = new \SplObjectStorage();
        $this->ackStore = [];
        $this->replyStore = [];
    }
    /**
     * Sends a DataPacket to the interface, returns an unique identifier for the packet if $needACK is true
     *
     * @param Player $player
     * @param DataPacket $packet
     * @param bool $needACK
     * @param bool $immediate
     *
     * @return int
     */
    public function putPacket(Player $player, DataPacket $packet, $needACK = false, $immediate = true){
        if($player instanceof SpecterPlayer) {
            if ($packet instanceof TextPacket) {
                $type = "Unknown";
                switch($type){
                    case TextPacket::TYPE_CHAT:
                        $type = "Chat"; // warn about deprecation?
                        break;
                    case TextPacket::TYPE_RAW:
                        $type = "Message";
                        break;
                    case TextPacket::TYPE_POPUP:
                        $type = "Popup";
                        break;
                    case TextPacket::TYPE_TIP:
                        $type = "Tip";
                        break;
                    case TextPacket::TYPE_TRANSLATION:
                        $type = "Translation (with params: " . implode(", ", $packet->parameters) . ")";
                        break;
                }
                $this->specter->getLogger()->info(TextFormat::LIGHT_PURPLE . "$type to {$player->getName()}: " . TextFormat::WHITE . $packet->message);
            } elseif (get_class($packet) === "shoghicp\\FastTransfer\\StrangePacket") { // strange packet (transferring)
                $this->specter->getLogger()->info("Specter is requested to be transferred to $packet->address:$packet->port.");
                $player->close("", "client disconnect");
            } elseif ($packet instanceof StartGamePacket) {

            } elseif ($packet instanceof FullChunkDataPacket) {

            } elseif ($packet instanceof SetTimePacket) {

            } elseif ($packet instanceof SetSpawnPositionPacket) {

            } /*else {
                $this->specter->getLogger()->info("Specter encountered an unknown packet.");
            }
            */
            if($packet instanceof SetHealthPacket){
                if($packet->health <= 0){
                    if($this->specter->getConfig()->get("autoRespawn")){
                        $pk = new RespawnPacket();
                        $this->replyStore[$player->getName()][] = $pk;
                    }
                }
                else{
                    $player->spec_needRespawn = true;
                }
            }
            if($needACK){
                $id = count($this->ackStore[$player->getName()]);
                $this->ackStore[$player->getName()][] = $id;
                $this->specter->getLogger()->info("Created ACK.");
                return $id;
            }
        }
	    return null;
    }

    /**
     * Terminates the connection
     *
     * @param Player $player
     * @param string $reason
     *
     */
    public function close(Player $player, $reason = "unknown reason"){
        $this->sessions->detach($player);
        unset($this->ackStore[$player->getName()]);
        unset($this->replyStore[$player->getName()]);
    }

    /**
     * @param string $name
     */
    public function setName($name){
        // TODO: Implement setName() method.
    }
    public function openSession($username, $address = "SPECTER", $port = 19133){
        if(!isset($this->replyStore[$username])) {
            $player = new SpecterPlayer($this, null, $address, $port);
            $this->sessions->attach($player, $username);
            $this->ackStore[$username] = [];
            $this->replyStore[$username] = [];
            $this->specter->getServer()->addPlayer($username, $player);

            $pk = new LoginPacket;
            $pk->username = $username;
            $pk->protocol = Info::CURRENT_PROTOCOL;
            $pk->clientUUID = UUID::fromData($address, $port, $username);
            $pk->clientId = 1;
            $pk->skin = str_repeat("\x80", 64 * 32 * 4);

            $player->handleDataPacket($pk);

            return true;
        }
        else{
            return false;
        }
    }
    /**
     * @return bool
     */
    public function process(){
        foreach($this->ackStore as $name => $acks){
            $player = $this->specter->getServer()->getPlayer($name);
            if($player instanceof SpecterPlayer){
	            /** @noinspection PhpUnusedLocalVariableInspection */
	            foreach($acks as $id){
//                    $player->handleACK($id); // TODO method removed. THough, Specter shouldn't have ACK to fill.
                    $this->specter->getLogger()->info("Filled ACK.");
                }
            }
            $this->ackStore[$name] = [];
        }
        foreach($this->replyStore as $name => $packets){
            $player = $this->specter->getServer()->getPlayer($name);
            if($player instanceof SpecterPlayer){
                foreach($packets as $pk){
                    $player->handleDataPacket($pk);
                    $this->specter->getLogger()->info("Sent packet.");
                }
            }
            $this->replyStore[$name] = [];
        }
        return true;
    }
    public function queueReply(DataPacket $pk, $player){
        $this->replyStore[$player][] = $pk;
    }
    public function shutdown(){
        // TODO: Implement shutdown() method.
    }

    public function emergencyShutdown(){
        // TODO: Implement emergencyShutdown() method.
    }
}
