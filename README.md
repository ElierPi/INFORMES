# Healthcare Regulatory Reporting Platform

A healthcare regulatory reporting platform built with **Laravel 12 and Livewire 4** to automate the processing, validation, correction and preparation of health reports submitted by healthcare providers (IPS).

The platform helps transform complex Excel, TXT and ZIP-based reporting workflows into structured, reusable and maintainable software processes.

## Overview

Healthcare providers (IPS) must periodically prepare and submit structured health reports to EPS and other entities within the Colombian healthcare system.

These reports must comply with specific technical structures, validation rules, field formats and reporting requirements.

This application automates different stages of that workflow, including:

- Importing Excel, TXT and ZIP reports
- Detecting and validating report structures
- Mapping and normalizing fields
- Applying business and validation rules
- Identifying inconsistent or missing information
- Correcting predefined reporting errors
- Removing invalid or duplicate records when required
- Preparing datasets for submission
- Generating Excel, TXT and ZIP output files
- Producing validation summaries and processing results

## Reporting Workflows

The platform supports multiple healthcare reporting processes, including workflows based on regulatory resolutions and organization-specific reporting requirements.

Examples include:

- Regulatory healthcare reports
- Chronic disease reporting
- Maternal health reporting
- High-cost account reporting
- EPS-specific validation and correction workflows
- Structured Excel and TXT submissions

## Processing Architecture

```text
IPS Report
    │
    ▼
File Import
    │
    ▼
Structure Detection
    │
    ▼
Field Mapping
    │
    ▼
Normalization
    │
    ▼
Validation Engine
    │
    ├── Errors
    ├── Warnings
    └── Valid Records
    │
    ▼
Business Rules
    │
    ▼
Correction / Transformation
    │
    ▼
Report Preparation
    │
    ▼
Export
    ├── Excel
    ├── TXT
    └── ZIP
```

## Data Privacy

This repository contains the application source code only.

Real patient information, medical records and personally identifiable healthcare data must **not be included in the public repository**.

Development examples and tests should use anonymized or synthetic datasets.
