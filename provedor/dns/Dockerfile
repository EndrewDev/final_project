# dns/Dockerfile
FROM ubuntu:latest

RUN apt-get update && apt-get install -y bind9

COPY named.conf.local /etc/bind/named.conf.local

COPY db.welcome.com.br /etc/bind/db.welcome.com.br

EXPOSE 53/tcp
EXPOSE 53/udp

CMD ["named", "-g"]
