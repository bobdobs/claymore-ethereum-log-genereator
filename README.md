# Claymore Ethereum log genereator
Generate log files from Claymores Dual Ethereum Miner and output the data in a new format 
 that is easy to work with. The script has been tested on Claymore v9.5 and v9.7.


## Motivation
My goal is to get a better visual representation of the data from the Claymore miner. I will 
achieve this by creating a cool looking Grafana or Kibana dashboard. This is what my current 
 Grafana dashboard looks like: 
 
 ![Alt text](./grafana-dashboard/grafana-screenshot_01.png?raw=true "Dashboard") 

Look here for more inspiration: 

https://grafana.com/  
https://www.elastic.co/products/kibana

I really needed a better visual representation of the data to optimize my rig that is sitting 
inside a 4U server. Temperature is a real challenge and a better visual representation of fan 
speed, temperature etc. really helps. Alerts in Grafana are also nice. 

I was not totally happy about the log format that the Claymore miner provides, so I decided 
to write my own log generator. It outputs much of the same information, but in a different 
format that is easier to work with in the ELK stack (Elasticsearch, Logstash, Kibana) or 
the TICK stack (Telegraf, InfluxDB, Chronograf, and Kapacitor + Grafana). 

It is also possible to configure the script to write directly to InfluxDB. You just need to 
have InfluxDB installed and with a database called "telegraf".


## How it works
The scripts queries the Claymore miner API with cURL and parses the result using PHP. 
It then outputs to two separate log files: 
- A log-file with totals (like total mining speed, total shares, avg. temperature etc.).
- A log-file with metrics about individual GPU's in the rig. 

Take a look at the sample log files in the log_examples folder to see what the log files look 
like.

The script also writes to the telegraf database in InfluxDB using cURL. This is enabled by 
default in the script. So if you have installed InfluxDB and Grafana and just want to see 
the metrics on the Grafana dashboard, you don't really need the generated log files.

## Setup and requirements 
Simply download or clone repository. The only requirement is PHP with cURL. The script is 
tested and works fine on both Linux and Windows.  

### Windows
You are on you own :-)

### Linux
Make the php file executable:  

`$ chmod +x eth_log_gen.php`

I assume you have PHP installed, but you may need to install cURL for PHP. You can check if it 
is installed with PHP-info.:   

`php -i | grep curl`

To install cURL for PHP:  

`$ sudo apt-get install php-curl`

You should configure "eth_log_gen.php" so it fit you needs. Please see the section "Configuring" 
below. 

You are now ready to run "eth_log_gen.php":

`$ /usr/bin/php ./eth_log_gen.php`

If your miner is running you should see the log-files growing. Terminate with Ctrl+c.

To ensure the script runs continuously and starts automatically when your OS boots, you should 
run it using supervisord. You can install supervisord like this:

`$ sudo apt install supervisor`

Then copy the file eth_log_gen.conf to the supervisord config directory:

`$ sudo cp eth_log_gen.conf /etc/supervisor/conf.d/eth_log_gen.conf`

Don't forget to modify the settings in the file: 

`$ sudo nano /etc/supervisor/conf.d/eth_log_gen.conf`

Finally you will need to start (or restart) supervisord: 
`$ sudo service supervisor status`

You can verify that eth_log_gen.php is running with supervisorctl: 

`$ sudo supervisorctl status`

Finally you may want to install InfluxDB, Telegraf and Grafana. Please see: 

  - https://portal.influxdata.com/downloads
  - https://grafana.com/get


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
 - define('INFLUXDB_ENABLED', true);
 \# Set to false if you don't have InfluxDB installed
 - define('INFLUXDB_DB', 'telegraf');
 \# This is the InfluxDB database that the script will write to 
 - define('INFLUXDB_SERVICE_URL', 'http://localhost');
 \# The default URL where InfluxDB can be reached if installed locally
 - define('INFLUXDB_SERVICE_PORT', 8086);
 \# The default port where InfluxDB can be reached

You will want to set RUN_INDEFINITELY=true in order to continuously update the log-files.

  
## Donate
You are free to use the script in any way you desire. If you found it useful consider making 
a donation: 

ETH: 0xa88F6aE3370205d453dEEfef06AafefEe4942710  
BTC: 1Dpb53d2xTHr5AC55MUQU6yBWNcRt8k3zG  
Zcash: t1PLbzngKSNYZWo47va15tsmygjLfKx8cro  
