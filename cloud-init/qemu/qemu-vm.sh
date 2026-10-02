#!/bin/bash

genisoimage \
    -output seed.img \
    -volid cidata -rational-rock -joliet \
    /cloud-init/user-data \
    /cloud-init/meta-data  \
    /cloud-init/network-config  


qemu-system-x86_64 -m 1024 -net nic -net user \
    -drive file=noble-server-cloudimg-amd64.img,index=0,format=qcow2,media=disk \
    -drive file=seed.img,index=1,media=cdrom \
    -machine accel=kvm:tcg