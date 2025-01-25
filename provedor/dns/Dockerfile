# dns/Dockerfile
FROM ubuntu:latest

RUN apt-get update && apt-get install -y bind9

COPY named.conf.local /etc/bind/named.conf.local

COPY db.endrew.asa.br /etc/bind/db.endrew.asa.br

EXPOSE 53/tcp
EXPOSE 53/udp

CMD ["named", "-g"]
