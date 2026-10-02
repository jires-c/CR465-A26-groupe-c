#!/bin/bash
#telecharge l image
curl -LO https://cloud-images.ubuntu.com/noble/current/noble-server-cloudimg-amd64.img >cloud-init
#creer le repertoire pour le disque de la vm 
mkdir  vms
#cp noble-server-clouding-amd64.img  ubuntu-groupec.qcow2
#covertir l image en qcow2
#

qemu-img convert \
    -f qcow2 \
    -O qcow2 \
    cloud-init/noble-server-cloudimg-amd64.img \
    ~/vms/ubuntu01.qcow2

#augmente l espace disque 
qemu-img resize vms/ubuntu01.qcow2 20G
# cree l image iso pour qemu en chargant les informations de cloud-init
cloud-localds \
    ~/cloud-init/seed.iso \
    ~/cloud-init/user-data \
    ~/cloud-init/meta-data
#creer la vm avec les nformations necessaires 
qemu-system-x86_64 \
    -enable-kvm \
    -m 2G \
    -smp 2 \
    -drive file=vms/ubuntu.qcow2,format=qcow2 \
    -drive file=~/cloud-init/seed.iso,media=cdrom \
    -net nic \
    -net user \
    -nographic

