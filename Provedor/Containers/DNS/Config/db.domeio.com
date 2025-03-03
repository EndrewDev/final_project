;
; BIND data file for local loopback interface
;
$ORIGIN domeio.com.
$TTL	604800

@   IN  SOA ns.domeio.com. root.domeio.com. (
		2024030401		; Serial
			 604800		; Refresh
			  86400		; Retry
			2419200		; Expire
			 604800 )	; Negative Cache TTL
;
@	IN	NS	ns.domeio.com.
@	IN	MX	10	domail.domeio.com.
@	IN	A	192.168.0.8

ns      IN  A   192.168.0.8
domail  IN  A   192.168.0.8
webmail IN  A   192.168.0.8
www     IN  A   192.168.0.8
proxy   IN  CNAME www
