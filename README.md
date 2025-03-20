# TYPO3 Extension `Academic person database - frontend editing` (READ-ONLY)

|                  | URL                                                                        |
|------------------|----------------------------------------------------------------------------|
| **Repository:**  | https://github.com/fgtclb/academic-persons-edit                            |
| **Read online:** | https://docs.typo3.org/p/fgtclb/academic/academic-persons-edit/main/en-us/ |
| **TER:**         | https://extensions.typo3.org/extension/academic_persons_edit/              |

## Description

This extension extends the `academic_persons` extension by the option to edit profiles in the frontend.
Profiles get connected with a frontend user and the frontend user is allow to edit its assigned profiles.

> [!NOTE]
> This extension is currently in beta state - please notice that there might be changes to the structure

## Compatibility

| Branch | Version   | TYPO3       | PHP                                     |
|--------|-----------|-------------|-----------------------------------------|
| main   | 2.0.x-dev | ~v12 + ~v13 | 8.1, 8.2, 8.3, 8.4 (depending on TYPO3) |
| 1      | 1.2.x-dev | v11 + ~v12  | 8.1, 8.2, 8.3, 8.4 (depending on TYPO3) |

> [!IMPORTANT]
> The 2.x TYPO3 v12 and v13 support is not guaranteed over all extensions
> yet and will most likely not get it. It has only been allowed to install
> all of them with 1.x also in a TYPO3 v12 to combining them in the mono
> repository.
> Support in work and at least planned to be archived when releasing `2.0.0`.

## Installation

Install with your flavour:

* [TER](https://extensions.typo3.org/extension/academic_persons_edit/)
* Extension Manager
* composer

We prefer composer installation:
```bash
composer req fgtclb/academic-persons-edit
```

## Credits

This extension was created by [FGTCLB GmbH](https://www.fgtclb.com/).

[Find more TYPO3 extensions we have developed](https://github.com/fgtclb/).
