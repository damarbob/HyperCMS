# Hyper CMS

Welcome to **Hyper CMS**—a cutting-edge content management system that redefines how you build, manage, and maintain web content. Leveraging PHP 8 and CodeIgniter 4, Hyper CMS offers a hyper-customizable platform built around a dynamic fields concept, empowering both developers and content creators to tailor models, inputs, and entries to their exact needs. Designed with a sleek, responsive user interface and a robust modular architecture, Hyper CMS is your gateway to an adaptable and efficient web development experience.

## Table of Contents

- [Overview](#overview)
- [Key Components](#key-components)
  - [Models](#models)
  - [Fields](#fields)
  - [Items/entries](#itemsentries)
- [Documentation](#documentation)
- [Upgrading from 0.1](#upgrading-from-01)
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

## Upgrading from 0.1

HyperCMS 0.1 kept its data layer inside the app. This version reads the database through the StarDust package, which stores entry fields in another format and needs two columns the old database does not have. One command brings a 0.1 database up to date.

1. Back up the database, and check that the backup restores.
2. Deploy this version of the code and run `composer install`.
3. See what would change. Nothing is written.

   ```
   php spark hyper:upgrade --dry-run
   ```

4. Run the upgrade. It asks you to type the database name before it starts.

   ```
   php spark hyper:upgrade
   ```

The command records the table migration under StarDust's name, runs the migrations that are still pending, rewrites entry fields from `[{"id": "title", "value": "x"}]` to `{"title": "x"}`, and links each entry and model to its current version. Trashed entries are converted too, and no modified date changes. It skips work that is already done, so it is safe to run again after a failure.

Two things can need a second look. An entry version whose fields are a list in some other shape is left as it is, and the command names its id and ends with an error. The sort columns that speed up sorting by a field are built with MariaDB syntax. The upgrade has been tested on MariaDB 10.4.32, where it builds them. On MySQL, which we tested on 8.0.13, the command reports them as missing and the site works without them, with slower sorting by a field.

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
