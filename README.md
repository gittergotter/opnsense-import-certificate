# opnsense-import-certificate
Script to import an SSL certificate into a running OPNsense system and use it for the web GUI.

## usage

Run on the OPNsense host as root:

```
Usage: php opnsense-import-certificate.php /path/to/certificate.crt /path/to/private/key.pem cert_hostname.domain.tld
```

- Use the full certificate chain and an unencrypted PEM key.
- A renewed certificate replaces the entry this script created earlier. An already imported certificate is left as is.
- The hostname is only used in the configuration history. It is not checked against the certificate.
- Exits with 1 on errors, including when the configuration could not be saved. The web GUI is restarted only after a successful import.

## automation example with certbot

Copy the script to `/root` on the OPNsense host. Then add a deploy hook under `[renewalparams]` in `/etc/letsencrypt/renewal/yourdomain.tld.conf`:

```
renew_hook = "scp /etc/letsencrypt/live/yourdomain.tld/fullchain.pem /etc/letsencrypt/live/yourdomain.tld/privkey.pem root@youropnsensehost:/root/ && ssh root@youropnsensehost 'php /root/opnsense-import-certificate.php /root/fullchain.pem /root/privkey.pem yourhost.domain.tld && rm -f /root/fullchain.pem /root/privkey.pem'"
```
# opnsense-import-certificate
Script to import an SSL certificate into a running opnsense system

## usage


```
Usage: php opnsense-import-certificate.php /path/to/certificate.crt /path/to/private/key.pem cert_hostname.domain.tld
```

## automation example with dehydrated acme

```
./dehydrated -c -f config-lan
# push cert to opnsense
scp opnsense-import-certificate.php certs/yourhost.domain.tld/privkey.pem certs/yourhost.domain.tld/fullchain.pem root@youropnsensehost: \
&& ssh root@youropnsensehost 'php opnsense-import-certificate.php /root/fullchain.pem /root/privkey.pem yourhost.domain.tld'
```
