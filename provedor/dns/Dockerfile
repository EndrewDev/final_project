# dns/Dockerfile
FROM ubuntu:latest

RUN apt update -y
RUN apt-get update
RUN apt upgrade -y
RUN apt install vim net-tools -y
RUN apt install bind9 dnsutils -y

# COPY named.conf.local /etc/bind/named.conf.local

# COPY db.welcome.com.br /etc/bind/db.welcome.com.br

EXPOSE 53/tcp
EXPOSE 53/udp

CMD ["/usr/sbin/named", "-g", "-c", "/etc/bind/named.conf", "-u", "bind"]
