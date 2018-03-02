<?php
namespace specter\network;

use pocketmine\entity\Skin;
use pocketmine\network\mcpe\protocol\BatchPacket;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\LoginPacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\network\mcpe\protocol\PacketPool;
use pocketmine\network\mcpe\protocol\PlayStatusPacket;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\RequestChunkRadiusPacket;
use pocketmine\network\mcpe\protocol\ResourcePackClientResponsePacket;
use pocketmine\network\mcpe\protocol\ResourcePacksInfoPacket;
use pocketmine\network\mcpe\protocol\RespawnPacket;
use pocketmine\network\mcpe\protocol\SetHealthPacket;
use pocketmine\network\mcpe\protocol\StartGamePacket;
use pocketmine\network\mcpe\protocol\TextPacket;
use pocketmine\network\mcpe\protocol\SetTitlePacket;
use pocketmine\network\SourceInterface;
use pocketmine\Player;
use pocketmine\utils\BinaryStream;
use pocketmine\utils\TextFormat;
use pocketmine\utils\UUID;
use specter\Specter;

class SpecterInterface implements SourceInterface{
    /** @var  SpecterPlayer[]|\SplObjectStorage */
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

    public function start(){
        //NOOP
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
    public function putPacket(Player $player, DataPacket $packet, bool $needACK = false, bool $immediate = true){
        if($player instanceof SpecterPlayer) {
            //$this->specter->getLogger()->info(get_class($packet));
            switch (get_class($packet)){
                case ResourcePacksInfoPacket::class:
                    $pk = new ResourcePackClientResponsePacket();
                    $pk->status = ResourcePackClientResponsePacket::STATUS_COMPLETED;
                    $pk->handle($player->getSessionAdapter());
                    break;
                case TextPacket::class:
                	/** @var TextPacket $packet */
                    $type = "Unknown";
                    switch($packet->type){
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
                    break;
                case SetHealthPacket::class:
                	/** @var SetHealthPacket $packet */
                    if($packet->health <= 0){
                        if($this->specter->getConfig()->get("autoRespawn")){
                            $pk = new RespawnPacket();
                            $this->replyStore[$player->getName()][] = $pk;
                        }
                    }
                    else{
                        $player->spec_needRespawn = true;
                    }
                    break;
                case StartGamePacket::class:
                    $pk = new RequestChunkRadiusPacket();
                    $pk->radius = 8;
                    $this->replyStore[$player->getName()][] = $pk;
                    break;
                case PlayStatusPacket::class:
                	/** @var PlayStatusPacket $packet */
                    switch ($packet->status){
                        case PlayStatusPacket::PLAYER_SPAWN:
                            /*$pk = new MovePlayerPacket();
                            $pk->x = $player->getPosition()->x;
                            $pk->y = $player->getPosition()->y;
                            $pk->z = $player->getPosition()->z;
                            $pk->yaw = $player->getYaw();
                            $pk->pitch = $player->getPitch();
                            $pk->bodyYaw = $player->getYaw();
                            $pk->onGround = true;
                            $pk->handle($player);*/
                            break;
                    }
                    break;
                case MovePlayerPacket::class:
                	/** @var MovePlayerPacket $packet */
                    $eid = $packet->entityRuntimeId;
                    if($eid === $player->getId() && $player->isAlive() && $player->spawned === true && $player->getForceMovement() !== null) {
                        $packet->mode = MovePlayerPacket::MODE_NORMAL;
                        $packet->yaw += 25; //FIXME little hacky
                        $this->replyStore[$player->getName()][] = $packet;
                    }
                    break;
                case BatchPacket::class:
                	/** @var BatchPacket $packet */
                    $packet->offset = 1;
                    $packet->decode();

                    foreach($packet->getPackets() as $buf){
                        $pk = PacketPool::getPacketById(ord($buf{0}));
                        //$this->specter->getLogger()->info("PACK:" . get_class($pk));
                        if(!$pk->canBeBatched()){
                            throw new \InvalidArgumentException("Received invalid " . get_class($pk) . " inside BatchPacket");
                        }

                        $pk->setBuffer($buf, 1);
                        $this->putPacket($player, $pk, false, $immediate);
                    }
                    break;
		case SetTitlePacket::class:
		    $this->specter->getLogger()->info(TextFormat::LIGHT_PURPLE . "Title to {$player->getName()}: " . TextFormat::WHITE . $packet->text);
		    break;
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
    public function close(Player $player, string $reason = "unknown reason"){
        $this->sessions->detach($player);
        unset($this->ackStore[$player->getName()]);
        unset($this->replyStore[$player->getName()]);
    }

    /**
     * @param string $name
     */
    public function setName(string $name){
        // TODO: Implement setName() method.
    }
    public function openSession($username, $address = "SPECTER", $port = 19133){
        if(!isset($this->replyStore[$username])) {
            $player = new SpecterPlayer($this, null, $address, $port);
            $this->sessions->attach($player, $username);
            $this->ackStore[$username] = [];
            $this->replyStore[$username] = [];
            $this->specter->getServer()->addPlayer($player);

            $pk = new class() extends LoginPacket{
                public function decodeAdditional(){
                }
            };
            $pk->username = $username;
            $pk->protocol = ProtocolInfo::CURRENT_PROTOCOL;
            $pk->clientUUID = UUID::fromData($address, $port, $username)->toString();
            $pk->clientId = 1;
	     $pk->xuid = "xuid here";
            $pk->identityPublicKey = "key here";
            $pk->clientData["SkinId"] = "Specter";
            $pk->clientData["SkinData"] = base64_encode(str_repeat("\x80", 64 * 32 * 4));
            $pk->skipVerification = true;

            $pk->handle($player->getSessionAdapter());

            return true;
        }
        else{
            return false;
        }
    }
    /**
     * @return bool
     */
    public function process() : bool{
        foreach($this->ackStore as $name => $acks){
            $player = $this->specter->getServer()->getPlayer($name);
            if($player instanceof SpecterPlayer){
	            /** @noinspection PhpUnusedLocalVariableInspection */
	            foreach($acks as $id){

	                //$player->handleACK($id); // TODO method removed. Though, Specter shouldn't have ACK to fill.
                    $this->specter->getLogger()->info("Filled ACK.");
                }
            }
            $this->ackStore[$name] = [];
        }
        /**
         * @var string $name
         * @var DataPacket[] $packets
         */
	    foreach($this->replyStore as $name => $packets){
            $player = $this->specter->getServer()->getPlayer($name);
            if($player instanceof SpecterPlayer){
                foreach($packets as $pk){
                    $pk->handle($player->getSessionAdapter());
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
