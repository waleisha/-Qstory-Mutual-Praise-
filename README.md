# 随缘互赞 Qstory-Mutual-Praise
基于 QS story 规范的云端自动化互赞系统。模拟真人点赞频率以有效防止账号冻结 ，解决 Java Crash 异常 ，包含 PHP 后端派单逻辑与每日额度控制。  An automated mutual praise system based on QS story. Features simulated human behavior to prevent account freezing  and high-stability Java implementation.

> **版本:** 1.1 
> **作者:** 林初静 
> **日期:** 2026-02-22 
> **标签:** 功能扩展 

本项目是一个基于 C/S 架构的自动化互赞脚本与后端派单系统。旨在提供一个安全、稳定的自动化名片点赞解决方案。

## 🎯 平台兼容与适配说明 (QS story)

**适用平台:** 本插件主要针对 QQ 机器人/相关框架平台开发。
**QS story 适配:** 建议所有使用者在部署和修改时，请按 `QS story` 模块的最新规范进行适配（当前标明适用版本：v1.1）。确保模块加载与生命周期管理符合最新标准，以避免因版本差异导致的加载异常或崩溃。

## ✨ 核心特性

* **防冻结机制:** 脚本点赞频率深度模拟真人操作轨迹，有效解决因频繁、机械操作导致的账号风控与冻结问题。
* **高稳定性:** 优化了底层逻辑，彻底解决了加载脚本时偶尔出现的 Java Crash 异常，保证后台平稳运行。
* **智能派单后端:** PHP 后端采用严格的每日额度控制与冷却时间（Cooldown）风控算法，杜绝单日点赞异常超标。

## 🚀 部署与使用指南

### 后端部署 (PHP + MySQL)
1. 将 `server/` 目录下的 `praise.php` 上传至你的服务器。
2. 导入 `server/database.sql` 到你的 MySQL 数据库中。
3. **重要:** 编辑 `praise.php` 文件顶部的数据库配置信息，替换为你自己的数据库账密。

### 客户端配置
1. 将 `client/` 目录下的所有文件（`main.java`, `info.prop`, `desc.txt`）打包为你的插件格式。
2. 默认的 API 请求地址已在 `main.java` 的基础变量中配置为明文，请务必将其替换为你实际部署 PHP 脚本的服务器地址。
