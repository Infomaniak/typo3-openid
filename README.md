# Infomaniak Auth

## Installation

- composer req infomaniak/t3ext-infomaniak-auth
  - add the configuration to your config/sites/config.yaml
    ```yaml
    routeEnhancers:
      AuthLogin:
      type: Extbase
      namespace: tx_infomaniakauth_login
      defaultController: 'InfomaniakAuthLogin::login'
      routes:
        -
          routePath: '/login'
          _controller: 'InfomaniakAuthLogin::login'
        -
          routePath: '/oauth/callback'
          _controller: 'InfomaniakAuthLogin::callback'
    ```
- In the /typo3/module/tools/settings page, add your client id, client secret
  and scope
