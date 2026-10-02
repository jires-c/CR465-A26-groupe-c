#!/bin/bash
set -e
projetname="~/CR650-Groupe-C"
disque="groupe-c-dick.qcow2"
disquedir="~/${projetname}/vms"
# Création des répertoires
mkdir -p ${projetname}
mkdir -p ${disquedir}

rm -rfv ${projetname}/*
#telecharge l image

curl -L \
    -o ${projetname}/noble-server-cloudimg-amd64.img \
    https://cloud-images.ubuntu.com/noble/current/noble-server-cloudimg-amd64.img
#creer le repertoire pour le disque de la vm 
#mkdir  vms
#cp noble-server-clouding-amd64.img  ubuntu-groupec.qcow2
#covertir l image en qcow2
#
pwd
cp  ./cloud-init/meta-data ${projetname}/meta-data
cp ./cloud-init/user-data ${projetname}/user-data  #  network-config   paquet-lists   qemu   user-data 
cp ~/cloud-init/noble-server-cloudimg-amd64.img  ${disquedir}/${disque}


#qemu-img convert \
 #   -f qcow2 \
  #  -O qcow2 \
   # cloud-init/noble-server-cloudimg-amd64.img \
   # ~/vms/ubuntu01.qcow2

#augmente l espace disque 
qemu-img resize ${disquedir}/${disque}  20G
# cree l image iso pour qemu en chargant les informations de cloud-init
cloud-localds \
    ${projetname}/seed.iso \
    ${projetname}/user-data \
    ${projetname}/meta-data
#creer la vm avec les nformations necessaires 
qemu-system-x86_64 \
    -cpu qemu64 \
    -m 2G \
    -smp 2 \
    -drive if=virtio,file=${disquedir}/${disque},format=qcow2 \
    -drive file=${projetname}/seed.iso,media=cdrom \
    -netdev user,id=net0 \
    -device virtio-net-pci,netdev=net0 \
    -nographic

