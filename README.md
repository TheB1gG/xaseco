# Welcome to XASECO

This XASECO has fixes from many places and should run on current PHP versions.

Original authors: Flo, Assembler Maniac, Jfreu, [Xymph](https://www.xaseco.org/), [Undef](https://www.undef.name/)& others
Thanks for fixes to [Bueddl](https://ftp.bueddl.de/tm/php7_patches/), [Reaby](https://reaby.kapsi.fi/), Lecky and many more


# Usage

If you clone this repository it will not run without editing config files. Please take a look at [https://docs.xaseco.org/install.php](https://docs.xaseco.org/install.php) for the basics.

note:
you may want to use plugin.compatibledatabase_debian11.php instead of plugin.compatibledatabase.php if you come from a older os which used latin1 as default to create your database and migrate to a newer one which uses utf-8 as default or you could end up with all nicknames which use non ascii characters beeing displayed wrong.