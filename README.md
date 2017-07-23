Specter
=======
**Real people suck. Use fake ones instead.**

Specter eliminates the need to get additional people to test a plugin and the horrible alternative. Specter generates players which can be used by PocketMine and plugins as if they were real players.

### Managing players
Players can be managed via an API or through a command. 

#### API

```php
$dummy = new \specter\api\DummyPlayer("Playername");
$dummy->getPlayer()->sendMessage("hello");
$dummy->close();
```
#### Command
```bash
specter spawn Playername # The full command to spawn a new dummy
s s playername # Luckily there is shorthand
s c playername /spawn # Execute /spawn as player
```
#### Detailed Commands
(main command omitted)

### Spawning
```
s
add
new
spawn
```

### Removing
```
q
close
quit
kick
```

### Teleporting
```
m
tp
move
teleport
```

### Attacking
```
a
attack
```

### Chat/Command
```
c
chat
command
```

### ICU
```
icu
control
```

### Respawn
```
r
respawn
```

