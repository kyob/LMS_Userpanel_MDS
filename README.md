# MyDeviceSettings

TR-069 enables remote and safe configuration of network devices called CPE.

## Donation make my day :)

* Bitcoin (BTC): `bc1qvwahntcrwjtdp0ntfd0l6kdvdr9d9h6atp6qrr`
* Ethereum (ETH): `0xEFCd4b066195652a885d916183ffFfeEEd931f40`
* Bitcoin SV (BSV): `$kopiszka` (my HandCash handle)
* or any other cryptocurrency if you want just ask for wallet address

## WARNING!

* It's only development implementation it's not ready for production purpose.
* You use it at your own risk.

## Getting Started

These instructions will get you a copy of the project up and running on your local machine for development and testing purposes. See deployment for notes on how to deploy the project on a live system.

## Requirements

* [LMS](https://lms.org.pl/) or [LMS-PLUS](https://lms-plus.org) (recommended)
* [GenieACS](https://genieacs.com/) - [tested with Docker version](https://github.com/genieacs/genieacs/wiki/Docker-Installation-with-Docker-Compose)

### GenieACS

Add VirtualParameter with name *unifiedMAC* and script:
```
// Example: Unified MAC parameter across different device models
let m = "00:00:00:00:00:00";
let d = declare("InternetGatewayDevice.WANDevice.*.WANConnectionDevice.*.WANIPConnection.*.MACAddress", {value: Date.now()});

if (d.size) {
  for (let p of d) {
    if (p.value[0]) {
      m = p.value[0];
      break;
    }
  }
}

m=m.toLowerCase();

return {writable: false, value: [m, "xsd:string"]};
```

### Installation

What things you need to install the software and how to install them

* Go to the LMS-PLUS root directory `cd <PATH-TO-LMS-PLUS>`
* Next, run the Composer command to install the latest stable version of Guzzle `composer require guzzlehttp/guzzle`
  * You can then later update Guzzle using composer `composer update` or `composer update --no-dev`

## Links

* [LMS-PLUS](https://github.com/lmsgit/lms/wiki/Informacje-o-projekcie-LMS-Plus) - Lan Management System (LMS)
* [GenieACS](https://genieacs.com/) - Open source TR-069 remote management solution with advanced device provisioning capabilities
* [Guzzle](https://github.com/guzzle/guzzle) - PHP HTTP client that makes it easy to send HTTP requests and trivial to integrate with web services
* [kyob](https://kopiszka.com)

## Acknowledgments

* Hat tip to anyone whose code was used
