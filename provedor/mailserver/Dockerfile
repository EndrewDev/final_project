# Usando uma imagem base oficial do Ubuntu
FROM ubuntu:20.04

# Atualizando e instalando pacotes necessários
RUN apt-get update && apt-get install -y \
    postfix \
    dovecot-core \
    dovecot-imapd \
    dovecot-pop3d \
    mailutils \
    && rm -rf /var/lib/apt/lists/*

# Expondo as portas do servidor de e-mail
EXPOSE 25 587 143 993 110 995

# Definindo o comando de inicialização para o Postfix e Dovecot
CMD service postfix start && service dovecot start && tail -f /var/log/mail.log
