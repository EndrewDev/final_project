;
; BIND data file for welcome.com
;
$ORIGIN welcome.com.
$TTL    604800

@       IN  SOA ns.welcome.com. admin.welcome.com. (
                2024060101      ; Serial (formato AAAAMMDDXX)
                3600            ; Refresh
                1800            ; Retry
                1209600         ; Expire
                86400 )         ; Negative Cache TTL

; Nameservers
@       IN  NS  ns.welcome.com.

; Endereços IP
@       IN  A    192.168.0.8       ; IP do servidor DNS (provedor)
ns      IN  A    192.168.0.8       ; Nameserver
mail    IN  A    192.168.0.8       ; Servidor de e-mail
www     IN  A    192.168.0.8       ; Servidor web
web     IN  A    192.168.0.8       ; Servidores web
proxy   IN  CNAME www           ; Proxy reverso (aponta para www)
