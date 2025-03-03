FROM ubuntu:latest

RUN apt update && apt install -y openssh-server
RUN mkdir /var/run/sshd

#criar usuarios, mais tarde ver como fazer integração automática
RUN useradd -m praiano
RUN useradd -m praiano2

# Setar senhas
RUN echo 'root:password' | chpasswd
RUN echo "praiano:redes" | chpasswd
RUN echo "praiano2:redes" | chpasswd

# tirando login de root
RUN sed -i 's/PermitRootLogin prohibit-password/PermitRootLogin no/' /etc/ssh/sshd_config

RUN mkdir -p /root/.ssh && chmod 700 /root/.ssh

EXPOSE 22

CMD ["/usr/sbin/sshd", "-D"]
