# Hyper CMS

Welcome to **Hyper CMS**—a cutting-edge content management system that redefines how you build, manage, and maintain web content. Leveraging PHP 8 and CodeIgniter 4, Hyper CMS offers a hyper-customizable platform built around a dynamic fields concept, empowering both developers and content creators to tailor models, inputs, and entries to their exact needs. Designed with a sleek, responsive user interface and a robust modular architecture, Hyper CMS is your gateway to an adaptable and efficient web development experience.

## Table of Contents

- [Overview](#overview)
- [Key Components](#key-components)
  - [Models](#models)
  - [Fields](#fields)
  - [Items/entries](#itemsentries)
- [Documentation](#documentation)
- [Upgrading from an earlier version](#upgrading-from-an-earlier-version)
- [Vision](#vision)
- [Notes](#notes)
- [Contact](#contact)

## Overview

Hyper CMS is an innovative and hyper-customizable content management system designed to simplify web development by leveraging a dynamic fields concept. Developed using PHP 8 and the CodeIgniter 4 framework, Hyper CMS aims to empower users by providing flexibility in creating and customizing models within the system. Hyper CMS combines robustness with a sleek, responsive user interface.

![Hyper CMS Screenshot](https://t9018906321.p.clickup-attachments.com/t9018906321/82ce8a03-d765-4796-a3f5-68c0ccd8da9d/localhost_8080_admin_dashboard.png)

## Key Components

To master the core concepts of Hyper CMS, you only need to understand the following:

### Models

Central to Hyper CMS, models can be extended into various types such as Blog, Agenda, Gallery, etc. Each model is highly customizable, with a set of dynamic fields tailored to user requirements.

### Fields

Individual inputs are required to form a model. These fields are versatile and adaptable, converting into inputs in the Item Editor. The wide range of input types available ensures comprehensive customization.

### Entries/Items

Data entries based on created models. For instance, a Blog model allows users to write and manage blog posts as individual Blog Items.

## Documentation

The documentation is a work in progress and is publicly available in our internal ClickUp workspace. You can access it via the following ClickUp document. Please note that the documentation is not yet complete and may be missing some information. We apologize for any inconvenience.

[https://doc.clickup.com/9018906321/d/h/8ct35ph-1918/ab2db207224a8e4](https://doc.clickup.com/9018906321/d/h/8ct35ph-1918/ab2db207224a8e4)

## Upgrading from an earlier version

HyperCMS 0.1 kept its data layer inside the app, and the 0.2 alphas used StarDust 0.2. This version runs on StarDust 0.3. Models, entries and their versions now live in tables of HyperCMS (`hyper_models`, `hyper_entries` and so on), and the current fields of each entry live in StarDust's tables, which carry the prefix `sd_`. One command copies a 0.1 or 0.2 database into that layout.

The database server must be MySQL 8.0.13 or newer, or MariaDB 10.11 or newer. StarDust 0.3 refuses older servers, and the command stops with a message when it meets one.

1. Back up the database, and check that the backup restores.
2. Deploy this version of the code and run `composer install`.
3. See what the command would meet. Nothing is written.

   ```
   php spark hyper:upgrade --dry-run
   ```

4. Run the upgrade. It asks you to type the database name before it starts.

   ```
   php spark hyper:upgrade
   ```

The command creates the new tables, copies every model, entry and version with the same ids, so links and stored select values keep working, and writes the current fields of each live entry to StarDust. Fields saved as `[{"id": "title", "value": "x"}]` become `{"title": "x"}`. It then reads every entry back and compares it with its source. The old tables (`models`, `model_data`, `entries` and `entry_data`) are left untouched, and nothing drops them. Drop them yourself once you have restored a dump somewhere and are satisfied with the result. The command skips work that is already done, so it is safe to run again after a failure.

An entry version whose fields are a list in some other shape is copied as text and named in the output. An entry that does not fit `max_allowed_packet` is skipped and listed, and raising that setting lets a rerun move it.

StarDust finishes some work in the background, such as deleting a purged model, and HyperCMS finishes writes that a crash left halfway. Both run from one command, so schedule it every minute. Do not also run StarDust's own daemons.

```
* * * * * cd /path/to/hypercms && php spark hyper:tick
```

With Docker, the `tick` service does this. The web container stops at start while an old database has not finished its upgrade, and the tick service waits as well. Run the upgrade in a new container, for example `docker compose run --rm --entrypoint '' app php spark hyper:upgrade`, then start the stack. The tables of StarDust share the database with HyperCMS, so keep the `sd_` prefix in mind if other software uses the same database.

A script that makes the backup itself can pass `--yes` to skip the question.

## Vision

The vision for Hyper CMS is to become a leading content management system that champions flexibility, user empowerment, and seamless integration of modern technologies. By fostering a dynamic and interactive development environment, Hyper CMS aims to revolutionize the way users build and manage web content, setting a new standard for CMS platforms.

## Notes

- This repository is under rapid development.
- Expect breaking changes in every published commit.
- Stick to minor releases if you want to update.
- However, there may be little to no documentation on the update steps; we will do our best to address your issues privately if necessary.

## Contact

For donation inquiries, contribution guidelines, and to report security issues, please contact us at [hyper.cms@outlook.com](mailto:hyper.cms@outlook.com). We welcome your support and feedback, as it plays a crucial role in the ongoing development and security of Hyper CMS. Whether you have suggestions, questions, or concerns, don't hesitate to reach out. Your contributions help us continually improve the platform, and we look forward to collaborating with you.

---

© 2025 PT. Konstelasi Teknologi Internasional. All rights reserved.

Licensed under the Apache License, Version 2.0. See [LICENSE](./LICENSE) for details.
