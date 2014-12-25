Specter
=======
**Real people suck. Use fake ones instead.**

Specter eliminates the need to get additional people to test a plugin and the horrible alternative. Specter generates players which can be used by PocketMine and plugins as if they were real players.

### Managing players
Players can be managed via an API or through a command. 
#### API
```php
$dummy = new \spector\api\DummyPlayer("Playername");
$dummy->getPlayer()->sendMessage("hello");
$dummy->close();
```
#### Command
```
spector spawn Playername
s s playername //Shorthand
s c playername /spawn //Execute /spawn as player
```
