# Pimcore Bundle Manager

This library provides a way to install bundles via CLI command. It is a replacement for `bin/console pimcore:bundle:enable` which got removed in Pimcore 11.

To install a Pimcore bundle you can execute `vendor/bin/pimcore-bundle-enable BundleName`. As `BundleName` please use the name which you see in `bin/console pimcore:bundle:list`.

In Pimcore 11 your `config/bundles.php` will get edited according to [Pimcore documentation](https://pimcore.com/docs/platform/Pimcore/Extending_Pimcore/Add_Your_Own_Dependencies_and_Packages/#third-party-bundles).

The command is also compatible with Pimcore < 11. In this case Pimcore's `bin/console pimcore:bundle:enable` command gets executed.