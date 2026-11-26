#!/usr/bin/env python3
"""CR465: provisionnement idempotent des comptes Keycloak, sur la VM.
Aucun secret n'est imprime, stocke dans le YAML ou passe en argument CLI.
"""
import json
import os
import ssl
import sys
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path

ROOT = Path('/opt/GroupeC')
SECRETS = ROOT / 'secrets'
# Nom DNS officiel pour un SNI TLS correct; resolution locale via /etc/hosts.
BASE = 'https://keycloak.groupec.test:4443'
CTX = ssl.create_default_context(cafile=str(ROOT / 'traefik/certs/local.crt'))
OPENER = urllib.request.build_opener(urllib.request.ProxyHandler({}), urllib.request.HTTPSHandler(context=CTX))


def secret(name):
    return (SECRETS / name).read_text(encoding='utf-8').strip()


def request(method, path, token=None, payload=None, form=False):
    headers = {'Host': 'keycloak.groupec.test', 'Accept': 'application/json'}
    data = None
    if payload is not None:
        if form:
            data = urllib.parse.urlencode(payload).encode('utf-8')
            headers['Content-Type'] = 'application/x-www-form-urlencoded'
        else:
            data = json.dumps(payload, ensure_ascii=False).encode('utf-8')
            headers['Content-Type'] = 'application/json'
    if token:
        headers['Authorization'] = 'Bearer ' + token
    url = BASE + path
    req = urllib.request.Request(url, data=data, headers=headers, method=method)
    try:
        with OPENER.open(req, timeout=20) as resp:
            body = resp.read()
            return resp.status, (json.loads(body) if body else None)
    except urllib.error.HTTPError as exc:
        # Ne pas afficher un corps d'erreur potentiellement sensible.
        raise RuntimeError(f'Keycloak HTTP {exc.code} sur {method} {path.split("?")[0]}') from None


def login(username, password):
    status, result = request('POST', '/realms/master/protocol/openid-connect/token', payload={
        'grant_type': 'password', 'client_id': 'admin-cli',
        'username': username, 'password': password,
    }, form=True)
    if status != 200 or not result or not result.get('access_token'):
        raise RuntimeError('Authentification administrative impossible')
    return result['access_token']


def get_user(token, realm, username):
    q = urllib.parse.urlencode({'username': username, 'exact': 'true'})
    _, users = request('GET', f'/admin/realms/{realm}/users?{q}', token=token)
    matching = [x for x in users if x.get('username') == username]
    if len(matching) > 1:
        raise RuntimeError('Utilisateurs Keycloak en doublon')
    return matching[0] if matching else None


def create_user(token, realm, username, attrs):
    req_path = f'/admin/realms/{realm}/users'
    status, _ = request('POST', req_path, token=token, payload={'username': username, 'enabled': True, **attrs})
    if status not in (200, 201, 204):
        raise RuntimeError(f'Creation du compte {realm}/{username}: HTTP {status}')
    user = get_user(token, realm, username)
    if not user:
        raise RuntimeError(f'Creation non verifiable : {realm}/{username}')
    return user


def set_password(token, realm, user_id, password):
    status, _ = request('PUT', f'/admin/realms/{realm}/users/{user_id}/reset-password', token=token,
                        payload={'type': 'password', 'value': password, 'temporary': False})
    if status != 204:
        raise RuntimeError('Le mot de passe Keycloak n a pas ete enregistre')


def main():
    admin_password = secret('keycloak_permanent_admin_password')
    bootstrap_password = secret('keycloak_admin_password')
    test_password = secret('etudiant_test_password')
    # Preferer les droits durables si le compte temporaire a deja ete supprime.
    try:
        token = login('groupec-admin', admin_password)
        # Un compte partiellement cree peut avoir un mot de passe mais pas
        # encore le role admin: retenter alors avec le bootstrap.
        status, _ = request('GET', '/admin/realms/master', token=token)
        if status != 200:
            raise RuntimeError('Droits du compte permanent incomplets')
        using_bootstrap = False
    except RuntimeError:
        token = login('admin', bootstrap_password)
        using_bootstrap = True

    existing = get_user(token, 'master', 'groupec-admin')
    if not existing:
        if not using_bootstrap:
            raise RuntimeError('Admin permanent absent alors que bootstrap non accessible')
        existing = create_user(token, 'master', 'groupec-admin', {
            'firstName': 'Groupe', 'lastName': 'C Administrateur',
            'email': 'groupec-admin@groupec.test', 'emailVerified': True,
        })
        set_password(token, 'master', existing['id'], admin_password)
        print('Compte administrateur permanent cree.')
    elif using_bootstrap:
        # Si la creation du compte avait ete interrompue, restaurer le mot de passe attendu.
        set_password(token, 'master', existing['id'], admin_password)

    _, admin_role = request('GET', '/admin/realms/master/roles/admin', token=token)
    if not admin_role or not admin_role.get('id'):
        raise RuntimeError('Role admin du realm master introuvable')
    _, assigned = request('GET', f'/admin/realms/master/users/{existing["id"]}/role-mappings/realm', token=token)
    if not any(role.get('name') == 'admin' for role in assigned):
        status, _ = request('POST', f'/admin/realms/master/users/{existing["id"]}/role-mappings/realm',
                            token=token, payload=[admin_role])
        if status != 204:
            raise RuntimeError('Attribution role administrateur en echec')

    # Controle de droits EFFECTIF avant suppression du bootstrap.
    permanent_token = login('groupec-admin', admin_password)
    for realm in ('master', 'groupec'):
        status, _ = request('GET', f'/admin/realms/{realm}', token=permanent_token)
        if status != 200:
            raise RuntimeError(f'Droits administrateur permanents insuffisants sur {realm}')
    print('Administrateur permanent teste et autorise sur master + groupec.')

    test_user = get_user(permanent_token, 'groupec', 'etudiant-test')
    if not test_user:
        test_user = create_user(permanent_token, 'groupec', 'etudiant-test', {
            'firstName': 'Etudiant', 'lastName': 'Test',
            'email': 'etudiant-test@groupec.test', 'emailVerified': True,
        })
        set_password(permanent_token, 'groupec', test_user['id'], test_password)
        print('Compte de demonstration etudiant-test cree avec secret local.')
    else:
        print('Compte etudiant-test deja present; mot de passe conserve.')

    # Ne supprimer le bootstrap qu apres controle de connexion et des autorisations.
    bootstrap = get_user(permanent_token, 'master', 'admin')
    if bootstrap:
        if bootstrap['id'] == existing['id']:
            raise RuntimeError('Refus de supprimer le seul administrateur')
        status, _ = request('DELETE', f'/admin/realms/master/users/{bootstrap["id"]}', token=permanent_token)
        if status != 204:
            raise RuntimeError('Suppression du compte bootstrap en echec')
        if get_user(permanent_token, 'master', 'admin'):
            raise RuntimeError('Le compte bootstrap admin reste present')
        print('Compte bootstrap admin supprime apres verification du remplacement.')
    else:
        print('Compte bootstrap deja absent.')
    print('SUCCES Keycloak: admin permanent + utilisateur de test; secrets dans /opt/GroupeC/secrets.')


if __name__ == '__main__':
    try:
        main()
    except (RuntimeError, ValueError, OSError, urllib.error.URLError) as err:
        print('ERREUR provisionnement Keycloak: ' + str(err), file=sys.stderr)
        sys.exit(1)
