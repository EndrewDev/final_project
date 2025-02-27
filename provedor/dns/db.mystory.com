;
; BIND data file for mystory.com
;
$ORIGIN mystory.com.
$TTL    604800

@       IN  SOA ns.mystory.com. admin.mystory.com. (
                2024060101      ; Serial (formato AAAAMMDDXX)
                3600            ; Refresh
                1800            ; Retry
                1209600         ; Expire
                86400 )         ; Negative Cache TTL

; Nameservers
@       IN  NS  ns.mystory.com.

; Endereços IP
@       IN  A    192.168.56.1       ; IP do servidor DNS (provedor)
ns      IN  A    192.168.56.1       ; Nameserver
mail    IN  A    192.168.56.1       ; Servidor de e-mail
www     IN  A    192.168.56.1       ; Servidor web
web     IN  A    192.168.56.1       ; Servidores web
proxy   IN  CNAME www           ; Proxy reverso (aponta para www)
