FROM ubuntu:latest

#RUN echo "nameserver 8.8.8.8" > /etc/resolv.conf
RUN apt update --fix-missing -y
RUN apt upgrade -y
RUN apt install postfix dovecot-imapd dovecot-pop3d -y
RUN apt install -q -y syslog-ng
#RUN echo "nameserver 10.0.2.15" > /etc/resolv.conf

# Add users
RUN useradd -m usuario
RUN useradd -m usuario2
RUN echo "usuario:redes" | chpasswd
RUN echo "usuario2:redes" | chpasswd

# Criar diretório temporário para Maildir
RUN mkdir -p /maildata/usuario/Maildir /maildata/usuario2/Maildir && \
    chown -R usuario:usuario /maildata/usuario && \
    chown -R usuario2:usuario2 /maildata/usuario2 && \
    chmod -R 700 /maildata/*

# Copiar script de inicialização
COPY init.sh /init.sh
RUN chmod +x /init.sh

ENTRYPOINT ["/init.sh"]
