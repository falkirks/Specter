<?php
namespace specter\network;

use pocketmine\network\protocol\DataPacket;
use pocketmine\network\protocol\FullChunkDataPacket;
use pocketmine\network\protocol\Info;
use pocketmine\network\protocol\LoginPacket;
use pocketmine\network\protocol\LoginStatusPacket;
use pocketmine\network\protocol\RespawnPacket;
use pocketmine\network\protocol\SetHealthPacket;
use pocketmine\network\protocol\SetSpawnPositionPacket;
use pocketmine\network\protocol\SetTimePacket;
use pocketmine\network\protocol\StartGamePacket;
use pocketmine\network\protocol\MessagePacket;
use pocketmine\network\SourceInterface;
use pocketmine\Player;
use pocketmine\Server;
use specter\network\SpecterPlayer;
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
            if ($packet instanceof MessagePacket) {
                $this->specter->getLogger()->info("To {$player->getName()}: $packet->message");
            } elseif ($packet instanceof LoginStatusPacket) {

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
                    $pk = new RespawnPacket();
                    $this->replyStore[$player->getName()][] = $pk;
                }
            }
            if($needACK){
                $id = count($this->ackStore[$player->getName()]);
                $this->ackStore[$player->getName()][] = $id;
                $this->specter->getLogger()->info("Created ACK.");
                return $id;
            }
        }
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
            $pk->clientId = 1;
            $pk->loginData = "fake";
            $pk->protocol1 = Info::CURRENT_PROTOCOL;

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
                foreach($acks as $id){
                    $player->handleACK($id);
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
