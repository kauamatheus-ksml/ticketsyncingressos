// src/app/login/login.page.ts
import { Component } from '@angular/core';
import { IonicModule } from '@ionic/angular';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { HttpClient } from '@angular/common/http';

@Component({
  selector: 'app-login',
  standalone: true,
  imports: [CommonModule, IonicModule, FormsModule],
  templateUrl: './login.page.html',
  styleUrls: ['./login.page.scss'],
})
export class LoginPage {
  email: string = '';
  senha: string = '';
  
  // Altere para a URL real do seu endpoint de login
  private API_LOGIN = 'https://ticketsync.com.br/client_login.php';

  constructor(private router: Router, private http: HttpClient) {}

  doLogin() {
    if (!this.email || !this.senha) {
      alert('Preencha todos os campos.');
      return;
    }
    
    this.http.post<any>(this.API_LOGIN, { email: this.email, senha: this.senha })
      .subscribe(res => {
        console.log('Resposta de login:', res);
        if (res.success) {
          // Navega para a página de ingressos passando parâmetros via query string
          this.router.navigate(['/tickets'], { queryParams: { userEmail: res.email, clienteNome: res.nome } });
        } else {
          alert(res.message || 'Email ou senha inválidos.');
        }
      }, err => {
        console.error('Erro no login:', err);
        alert('Erro de conexão.');
      });
  }
}
