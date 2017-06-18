# Claymore Ethereum log genereator
Generate log files from Claymores Dual Ethereum Miner and output the data in a new format 
 that is easy to work with. The script has been tested on Claymore v9.4 and v9.5.


## Motivation
My goal is to get a better visual representation of the data from the Claymore miner. I will achieve this by creating a cool looking Grafana or Kibana dashboard. Look here for inspiration: 

https://grafana.com/  
https://www.elastic.co/products/kibana

I really needed a better visual representation of the data to optimize my rig that is sitting inside a 4U server. Temperature is a real challenge and a better visual representation of fan speed, temperature etc. really helps. 

I was not totally happy about the log format that the Claymore miner provides, so I decided 
to write my own log generator. It outputs much of the same information, but in a different 
format that is easier to work with in the ELK stack (Elasticsearch, Logstash, Kibana) or 
the TICK stack (Telegraf, InfluxDB, Chronograf, and Kapacitor + Grafana). 


## How it works
The scripts queries the Claymore miner API with cURL and parses the result using PHP. 
It then outputs to two separate log files: 
- A log-file with totals (like total mining speed, total shares, avg. temperature etc.).
- A log-file with metrics about individual GPU's in the rig. 

Take a look at the sample log files in the log_examples folder to see what the log files look like.  


## Setup and requirements 
Simply download or clone repository. The only requirement is PHP with cURL. The script is tested and works fine on both Linux and Windows.  

### Linux
Make the php file executable:  
`$ chmod +x eth_log_gen.php`

You may need to install cURL for PHP. You can check with PHP info.:   
`php -i | grep curl`

To install cURL for PHP:  
`$ sudo apt-get install php-curl`

### Configuring
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
 - define('DUAL_CURRENCY', 'DCR'); 
 \# Just leave as-is if NOT dual mining. Enter symbol for other cryptocurrency if dual mining (for example "DCR" for Decred)
 - define('DUAL_CURRENCY_LOWER', strtolower(DUAL_CURRENCY)); 
 \# Leave as-is

You will want to set RUN_INDEFINITELY=true in order to continuously update the log-files. Also you will most likely run this script from command line or as a daemon.   
 

## Upcoming changes
The script should run as daemon in Linux, and future version will provide an example script for this.
  
## Donate
You are free to use the script in any way you desire. If you found it useful consider making 
a donation: 

ETH: 0xa88F6aE3370205d453dEEfef06AafefEe4942710  
BTC: 1Dpb53d2xTHr5AC55MUQU6yBWNcRt8k3zG  
Zcash: t1PLbzngKSNYZWo47va15tsmygjLfKx8cro  
