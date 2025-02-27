;
; BIND data file for praia.com
;
$ORIGIN praia.com.
$TTL    604800

@       IN  SOA ns.praia.com. admin.praia.com. (
                2024060101      ; Serial (formato AAAAMMDDXX)
                3600            ; Refresh
                1800            ; Retry
                1209600         ; Expire
                86400 )         ; Negative Cache TTL

; Nameservers
@       IN  NS  ns.praia.com.

; Endereços IP
@       IN  A    192.168.56.1       ; IP do servidor DNS (provedor)
ns      IN  A    192.168.56.1       ; Nameserver
mail    IN  A    192.168.56.1       ; Servidor de e-mail
www     IN  A    192.168.56.1       ; Servidor web
web     IN  A    192.168.56.1       ; Servidores web
proxy   IN  CNAME www           ; Proxy reverso (aponta para www)
