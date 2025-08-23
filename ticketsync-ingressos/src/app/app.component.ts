// src/app/app.component.ts
import { Component } from '@angular/core';
import { IonicModule } from '@ionic/angular';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'app-root',
  standalone: true,
  imports: [CommonModule, IonicModule],
  template: `<ion-router-outlet></ion-router-outlet>`,
})
export class AppComponent {}
