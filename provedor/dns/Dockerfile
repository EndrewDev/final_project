# dns/Dockerfile
FROM ubuntu:latest

RUN apt-get update && apt-get install -y bind9

COPY named.conf.local /etc/bind/named.conf.local

COPY db.areiapreta.com.br /etc/bind/db.areiapreta.com.br

EXPOSE 53/tcp
EXPOSE 53/udp

CMD ["named", "-g"]
