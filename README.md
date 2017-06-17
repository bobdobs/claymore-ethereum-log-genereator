# Claymore Ethereum log genereator
Generate log files from Claymores Dual Ethereum Miner and output the data in a new format 
 that is easy to work with. The script has been tested on Claymore v9.4 and v9.5.

Please note that this is a work-in-progress!

## Motivation
I was not happy about the log format that Claymore Eth miner provides, so I decided 
to write my own log generator. It outputs much of the same information, but in a different 
format that is easier to work with in the ELK stack (Elasticsearch, Logstash, Kibana) or 
the TICK stack (Telegraf, InfluxDB, Chronograf, and Kapacitor + Grafana).


## How it works
The scripts queries the Claymore miner API with cURL and parses the result using PHP. 
It then outputs to two separate log files: 
- A log-file with totals (like total mining speed and total shares).
- A log-file with metrics about individual GPU's in the rig. 

The log files currently look like this: 
 
**Totals log** 

`2017-06-17T12:20:40Z02:00, machine: minr, eth_total_speed: 85.995, eth_total_shares: 1146, eth_total_rejected: 0, eth_avg_speed_per_card: 21.5, eth_avg_shares_per_card: 286.5, avg_temp: 63.25, avg_fan: 56.5`

**Individual GPU's log**

`2017-06-17T14:59:34Z02:00, machine: minr, id: GPU0, temp: 72, fan: 48, eth_speed: 27.229, eth_shares: 359`


## Configuring
Please use the constants at the top of eth_log_gen.php to configure various options. 
 
 - define('DEBUG_MODE', false);
 \# Set to true to output details to the screen
 - define('SERVICE_URL', 'http://192.168.1.186');
 \# Your mining machines IP (where Claymores API can be reached) 
 - define('SERVICE_PORT', 3333);
 \# Portnumber for Claymores API. 3333 is the default. 
 - define('RUN_INDEFINITELY', false); 
 \# The script is designed to run indefinitely and this can be enabled by setting this to true
 - define('UPDATE_FREQ', 10); 
 \# Defines how often Claymore API should be queried if the script is set to run indefinitely. 10 for every 10 seconds. 
 - define('MACHINE_NAME', 'minr');
 \# The name of your mining machine. This can be useful if you have multiple mining rigs
 - define('FILE_LOG_GPUS', 'gpu.log');
 \# The file name for the log with metrics about individual GPU's
 - define('FILE_LOG_TOTALS', 'totals.log');
 \# The file name for the logs containing totals 
 

## Upcoming changes
It currently works fine if running in dual mining mode, but does not provide data about the other currency mined. In the future it will. 
 
The script should run via daemon service in Linux, and future version will provide an example script for this.
  
## Donate
You are free to use the script in any way you desire. If you found it useful consider making 
a donation: 

ETH: 0xa88F6aE3370205d453dEEfef06AafefEe4942710  
BTC: 1Dpb53d2xTHr5AC55MUQU6yBWNcRt8k3zG  
Zcash: t1PLbzngKSNYZWo47va15tsmygjLfKx8cro  
