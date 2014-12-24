<?php
namespace specter\api;

use pocketmine\Server;
use specter\network\SpecterPlayer;
use specter\Specter;

class DummyPlayer{
    public function __construct($name, $address = null, $port = null){
        $this->name = $name;
        if(!$this->getSpecter()->getInterface()->openSession($name, $address, $port)){
            throw new \Exception("Failed to open session.");
        }
    }
    public function getPlayer(){
        $p = Server::getInstance()->getPlayer($this->name);
        if($p instanceof SpecterPlayer){
            return $p;
        }
        else{
            return null;
        }
    }
    /**
     * @return null|Specter
     * @throws \Exception
     */
    protected function getSpecter(){
        $plugin = Server::getInstance()->getPluginManager()->getPlugin("Specter");
        if($plugin !== null && $plugin->isEnabled()){
            return $plugin;
        }
        else{
            throw new \Exception("Specter is not available.");
        }
    }
}